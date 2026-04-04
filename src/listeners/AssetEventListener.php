<?php

namespace Noo\CraftCloudinary\listeners;

use Craft;
use craft\base\Event;
use craft\elements\Asset;
use craft\events\ModelEvent;
use Noo\CraftCloudinary\Cloudinary;

class AssetEventListener
{
    public static function register(): void
    {
        Event::on(
            Asset::class,
            Asset::EVENT_BEFORE_DELETE,
            [static::class, 'handleBeforeDelete'],
        );
    }

    public static function handleBeforeDelete(ModelEvent $event): void
    {
        /** @var Asset $asset */
        $asset = $event->sender;

        $syncGuard = Cloudinary::getInstance()->syncGuard;

        // When a webhook-initiated delete triggers Craft's deleteElement(),
        // Craft's afterDelete() would call $volume->deleteFile() which goes
        // through Flysystem back to Cloudinary's destroy API. But the file
        // is already deleted from Cloudinary (that's what triggered the webhook).
        // Setting keepFileOnDelete skips that redundant and potentially failing call.
        if ($syncGuard->isProcessingWebhook()) {
            $asset->keepFileOnDelete = true;
        }
    }
}
