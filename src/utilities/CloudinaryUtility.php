<?php

namespace Noo\CraftCloudinary\utilities;

use Craft;
use craft\base\Utility;
use craft\db\Query;
use craft\elements\Asset;
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
        return Craft::$app->getView()->renderTemplate('cloudinary/_utilities/cloudinary', [
            'volumes' => self::getCloudinaryVolumes(),
            'webhooks' => self::getRecentWebhooks(),
            'rateLimits' => self::getRateLimits(),
        ]);
    }

    public static function getRateLimits(): array
    {
        $limits = [];

        foreach (Craft::$app->getVolumes()->getAllVolumes() as $volume) {
            $fs = $volume->getFs();

            if (!$fs instanceof CloudinaryFs) {
                continue;
            }

            try {
                $response = $fs->getClient()->adminApi()->ping();
                $limits[] = [
                    'volumeName' => $volume->name,
                    'remaining' => $response->rateLimitRemaining,
                    'allowed' => $response->rateLimitAllowed,
                    'resetAt' => $response->rateLimitResetAt ? date('Y-m-d H:i:s T', $response->rateLimitResetAt) : null,
                ];
            } catch (\Throwable $e) {
                $limits[] = [
                    'volumeName' => $volume->name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $limits;
    }

    private static function getRecentWebhooks(int $limit = 50): array
    {
        return (new Query())
            ->select(['notificationType', 'publicId', 'processedAt'])
            ->from('{{%cloudinary_webhook_log}}')
            ->orderBy(['processedAt' => SORT_DESC])
            ->limit($limit)
            ->all();
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
