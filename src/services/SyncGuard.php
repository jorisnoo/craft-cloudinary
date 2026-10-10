<?php

namespace Noo\CraftCloudinary\services;

use Craft;
use yii\base\Component;

class SyncGuard extends Component
{
    /**
     * How long a change made from Craft keeps its echoing webhooks from
     * being applied.
     */
    private const CRAFT_CHANGE_TTL = 300;

    private bool $processingWebhook = false;

    public function whileProcessingWebhook(callable $fn): mixed
    {
        $this->processingWebhook = true;

        try {
            return $fn();
        } finally {
            $this->processingWebhook = false;
        }
    }

    public function isProcessingWebhook(): bool
    {
        return $this->processingWebhook;
    }

    /**
     * Keys on the asset key (see PublicIds::key()) rather than the filename,
     * so the fs write and the webhook agree even when Cloudinary reports a
     * format that differs from the uploaded extension. Scoped by cloud name
     * so equally named uploads to different Cloudinary accounts can't
     * suppress each other's webhooks.
     */
    public function markUploaded(string $cloudName, string $assetKey): void
    {
        Craft::$app->getCache()->set(
            $this->cacheKey('uploaded', $cloudName, $assetKey),
            true,
            self::CRAFT_CHANGE_TTL,
        );
    }

    public function wasUploadedFromCraft(string $cloudName, string $assetKey): bool
    {
        $key = $this->cacheKey('uploaded', $cloudName, $assetKey);

        if (!Craft::$app->getCache()->get($key)) {
            return false;
        }

        Craft::$app->getCache()->delete($key);

        return true;
    }

    /**
     * Records a move, rename or folder change Craft made in Cloudinary.
     *
     * Craft changes Cloudinary first and updates its own records afterwards,
     * so the webhooks echoing the change can arrive while Craft's records
     * still show the old state. Applying them then would duplicate folders
     * or delete assets Craft is in the middle of moving. Craft updates its
     * records itself, so these webhooks are skipped.
     *
     * @param string $subject an asset key (PublicIds::key()) or a folder key (folderKey())
     */
    public function markChangedFromCraft(string $cloudName, string $subject): void
    {
        Craft::$app->getCache()->set(
            $this->cacheKey('changed', $cloudName, $subject),
            true,
            self::CRAFT_CHANGE_TTL,
        );
    }

    /**
     * Unlike wasUploadedFromCraft(), this doesn't consume the mark: one
     * change from Craft can echo as several webhooks.
     */
    public function wasChangedFromCraft(string $cloudName, string $subject): bool
    {
        return (bool) Craft::$app->getCache()->get($this->cacheKey('changed', $cloudName, $subject));
    }

    public static function folderKey(?string $path): string
    {
        return 'folder:' . trim((string) $path, '/');
    }

    private function cacheKey(string $type, string $cloudName, string $subject): string
    {
        return "cloudinary:{$type}:{$cloudName}:{$subject}";
    }
}
