<?php

namespace Noo\CraftCloudinary\services;

use Craft;
use yii\base\Component;

class SyncGuard extends Component
{
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
     * Keys on the filename (with extension) rather than the public ID, so
     * raw files - whose public IDs keep the extension - hash the same from
     * both the fs write and the webhook. Scoped by cloud name so equally
     * named uploads to different Cloudinary accounts can't suppress each
     * other's webhooks.
     */
    public function markUploaded(string $cloudName, string $filename): void
    {
        Craft::$app->getCache()->set(
            $this->uploadCacheKey($cloudName, $filename),
            true,
            300,
        );
    }

    public function wasUploadedFromCraft(string $cloudName, string $filename): bool
    {
        $key = $this->uploadCacheKey($cloudName, $filename);

        if (!Craft::$app->getCache()->get($key)) {
            return false;
        }

        Craft::$app->getCache()->delete($key);

        return true;
    }

    private function uploadCacheKey(string $cloudName, string $filename): string
    {
        return "cloudinary:uploaded:{$cloudName}:{$filename}";
    }
}
