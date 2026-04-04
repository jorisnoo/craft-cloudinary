<?php

namespace Noo\CraftCloudinary\utilities;

use Craft;
use craft\base\Utility;
use craft\elements\Asset;
use Noo\CraftCloudinary\Cloudinary;
use Noo\CraftCloudinary\fs\CloudinaryFs;

class CloudinaryUtility extends Utility
{
    public static function displayName(): string
    {
        return 'Cloudinary';
    }

    public static function id(): string
    {
        return 'cloudinary';
    }

    public static function icon(): ?string
    {
        return dirname(__DIR__) . '/icon-mask.svg';
    }

    public static function contentHtml(): string
    {
        $plugin = Cloudinary::getInstance();
        $settings = $plugin->getSettings();

        return Craft::$app->getView()->renderTemplate('cloudinary/_utilities/cloudinary', [
            'volumes' => self::getCloudinaryVolumes(),
            'activity' => $plugin->activityLog->getRecent(),
            'thumbnailCacheEnabled' => $settings->enableThumbnailCache,
            'cacheStats' => $settings->enableThumbnailCache ? $plugin->thumbnailCache->getStats() : null,
        ]);
    }

    private static function getCloudinaryVolumes(): array
    {
        $volumes = [];

        foreach (Craft::$app->getVolumes()->getAllVolumes() as $volume) {
            $fs = $volume->getFs();

            if (!$fs instanceof CloudinaryFs) {
                continue;
            }

            $volumes[] = [
                'name' => $volume->name,
                'handle' => $volume->handle,
                'cloudName' => $fs->cloudName,
                'assetCount' => Asset::find()->volumeId($volume->id)->count(),
            ];
        }

        return $volumes;
    }
}
