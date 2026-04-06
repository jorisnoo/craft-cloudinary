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

    public function markUploaded(string $publicId): void
    {
        Craft::$app->getCache()->set(
            $this->uploadCacheKey($publicId),
            true,
            300,
        );
    }

    public function wasUploadedFromCraft(string $publicId): bool
    {
        $key = $this->uploadCacheKey($publicId);

        if (!Craft::$app->getCache()->get($key)) {
            return false;
        }

        Craft::$app->getCache()->delete($key);

        return true;
    }

    private function uploadCacheKey(string $publicId): string
    {
        return "cloudinary:uploaded:{$publicId}";
    }
}
