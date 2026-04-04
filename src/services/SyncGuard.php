<?php

namespace Noo\CraftCloudinary\services;

use Craft;
use yii\base\Component;

class SyncGuard extends Component
{
    private bool $processingWebhook = false;

    private bool $processingCraftEvent = false;

    public function whileProcessingWebhook(callable $fn): mixed
    {
        $this->processingWebhook = true;

        try {
            return $fn();
        } finally {
            $this->processingWebhook = false;
        }
    }

    public function whileProcessingCraftEvent(callable $fn): mixed
    {
        $this->processingCraftEvent = true;

        try {
            return $fn();
        } finally {
            $this->processingCraftEvent = false;
        }
    }

    public function isProcessingWebhook(): bool
    {
        return $this->processingWebhook;
    }

    public function isProcessingCraftEvent(): bool
    {
        return $this->processingCraftEvent;
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

    public function markSynced(string $publicId, string $operation): void
    {
        Craft::$app->getCache()->set(
            $this->syncCacheKey($publicId, $operation),
            true,
            300,
        );
    }

    public function wasSyncedFromCraft(string $publicId, string $operation): bool
    {
        $key = $this->syncCacheKey($publicId, $operation);

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

    private function syncCacheKey(string $publicId, string $operation): string
    {
        return "cloudinary:synced:{$operation}:{$publicId}";
    }
}
