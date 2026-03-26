<?php

namespace Noo\CraftCloudinary;

use Craft;
use craft\base\Event;
use craft\base\Model;
use craft\base\Plugin;
use craft\console\Controller;
use craft\elements\Asset;
use craft\events\DefineBehaviorsEvent;
use craft\events\DefineAssetThumbUrlEvent;
use craft\events\DefineConsoleActionsEvent;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\helpers\Queue;
use craft\helpers\UrlHelper;
use craft\services\Assets;
use craft\services\Fs;
use craft\services\ImageTransforms;
use craft\utilities\ClearCaches;
use Noo\CraftCloudinary\behaviors\CloudinaryUrlBehavior;
use Noo\CraftCloudinary\console\controllers\RemovePathsFromPublicIdsController;
use Noo\CraftCloudinary\console\controllers\ThumbnailCacheController;
use Noo\CraftCloudinary\console\controllers\TriggerAssetSyncController;
use Noo\CraftCloudinary\fs\CloudinaryFs;
use Noo\CraftCloudinary\imagetransforms\CloudinaryTransformer;
use Noo\CraftCloudinary\jobs\CacheThumbnail;
use Noo\CraftCloudinary\models\Settings;
use Noo\CraftCloudinary\services\ThumbnailCache;
use yii\log\FileTarget;

/**
 * @property ThumbnailCache $thumbnailCache
 */
class Cloudinary extends Plugin
{
    public string $schemaVersion = '1.0.0';

    public bool $hasCpSettings = true;

    public function init(): void
    {
        parent::init();

        $this->setComponents([
            'thumbnailCache' => ThumbnailCache::class,
        ]);

        Craft::$app->onInit(function() {
            $this->registerFilesystemTypes();
            $this->registerImageTransformers();
            $this->defineBehaviors();
            $this->registerConsoleCommands();
            $this->defineLogTarget();
            $this->registerThumbnailCaching();
            $this->registerCacheOptions();
        });
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('cloudinary/settings', [
            'settings' => $this->getSettings(),
        ]);
    }

    public function registerConsoleCommands(): void
    {
        // php craft cloudinary/trigger-asset-sync/sync [volumeId]
        Event::on(
            TriggerAssetSyncController::class,
            Controller::EVENT_DEFINE_ACTIONS,
            function(DefineConsoleActionsEvent $event) {
                $event->actions['sync'] = [
                    'helpSummary' => 'Sync Cloudinary asset volumes (all or by volume ID)',
                    'action' => function($params) {
                        $controller = Craft::$app->controller;
                        $controller->actionSync($params[0] ?? null);
                    },
                ];
            }
        );

        // php craft cloudinary/remove-paths-from-public-ids/scan 1
        Event::on(
            RemovePathsFromPublicIdsController::class,
            Controller::EVENT_DEFINE_ACTIONS,
            function(DefineConsoleActionsEvent $event) {
                $event->actions['remove-paths-from-public-ids'] = [
                    'helpSummary' => 'Scan all Cloudinary assets and remove paths from their public ids',
                    'action' => function($params) {
                        $controller = Craft::$app->controller;
                        $controller->actionScan($params);
                    },
                ];
            }
        );

        // php craft cloudinary/thumbnail-cache/clear
        // php craft cloudinary/thumbnail-cache/cleanup
        Event::on(
            ThumbnailCacheController::class,
            Controller::EVENT_DEFINE_ACTIONS,
            function(DefineConsoleActionsEvent $event) {
                $event->actions['clear'] = [
                    'helpSummary' => 'Clear the entire Cloudinary thumbnail cache',
                    'action' => function() {
                        $controller = Craft::$app->controller;
                        $controller->actionClear();
                    },
                ];
                $event->actions['cleanup'] = [
                    'helpSummary' => 'Remove expired entries from the Cloudinary thumbnail cache',
                    'action' => function() {
                        $controller = Craft::$app->controller;
                        $controller->actionCleanup();
                    },
                ];
            }
        );
    }

    private function registerFilesystemTypes(): void
    {
        Event::on(Fs::class, Fs::EVENT_REGISTER_FILESYSTEM_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = CloudinaryFs::class;
        });
    }

    private function registerImageTransformers(): void
    {
        Event::on(ImageTransforms::class, ImageTransforms::EVENT_REGISTER_IMAGE_TRANSFORMERS, function(RegisterComponentTypesEvent $event) {
            $event->types[] = CloudinaryTransformer::class;
        });
    }

    private function defineBehaviors(): void
    {
        Event::on(Asset::class, Asset::EVENT_DEFINE_BEHAVIORS, function(DefineBehaviorsEvent $event) {
            $volume = $event->sender->getVolume();
            $fs = $volume->getFs();
            $transformFs = $volume->getTransformFs();

            if ($fs instanceof CloudinaryFs || $transformFs instanceof CloudinaryFs) {
                $event->behaviors['cloudinary:url'] = CloudinaryUrlBehavior::class;
            }
        });
    }

    private function registerThumbnailCaching(): void
    {
        if (!$this->getSettings()->enableThumbnailCache) {
            return;
        }

        Event::on(Assets::class, Assets::EVENT_DEFINE_THUMB_URL, function(DefineAssetThumbUrlEvent $event) {
            $asset = $event->asset;
            $volume = $asset->getVolume();
            $fs = $volume->getFs();
            $transformFs = $volume->getTransformFs();

            if (!$fs instanceof CloudinaryFs && !$transformFs instanceof CloudinaryFs) {
                return;
            }

            if ($this->thumbnailCache->has($asset->id, $event->width, $event->height)) {
                $event->url = UrlHelper::actionUrl('cloudinary/thumbnails/serve', [
                    'assetId' => $asset->id,
                    'w' => $event->width,
                    'h' => $event->height,
                ]);
                return;
            }

            $cloudinaryUrl = $asset->getCloudinaryUrl([
                'width' => $event->width,
                'height' => $event->height,
                'crop' => 'fill',
                'gravity' => 'auto',
                'fetch_format' => 'auto',
                'quality' => 'auto',
            ]);

            $event->url = $cloudinaryUrl;

            if (!$this->thumbnailCache->tryMarkPending($asset->id, $event->width, $event->height)) {
                return;
            }

            Queue::push(new CacheThumbnail(
                assetId: $asset->id,
                width: $event->width,
                height: $event->height,
                cloudinaryUrl: $cloudinaryUrl,
            ));
        });
    }

    private function registerCacheOptions(): void
    {
        Event::on(ClearCaches::class, ClearCaches::EVENT_REGISTER_CACHE_OPTIONS, function(RegisterCacheOptionsEvent $event) {
            $event->options[] = [
                'key' => 'cloudinary-thumbnails',
                'label' => 'Cloudinary thumbnail cache',
                'action' => function() {
                    $this->thumbnailCache->invalidateAll();
                },
            ];
        });
    }

    protected function defineLogTarget(): void
    {
        $logTarget = new FileTarget();
        $logTarget->logFile = Craft::getAlias('@storage/logs/cloudinary.log');
        $logTarget->levels = ['error', 'warning', 'info'];
        $logTarget->categories = ['cloudinary'];
        $logTarget->maxFileSize = 10240; // 10MB per file before rotation
        $logTarget->maxLogFiles = 30; // Keep 30 rotated files
        $logTarget->logVars = [];

        Craft::$app->log->targets[] = $logTarget;
    }

    public static function log(string $message, string $level = 'info'): void
    {
        match ($level) {
            'error' => Craft::error($message, 'cloudinary'),
            'warning' => Craft::warning($message, 'cloudinary'),
            default => Craft::info($message, 'cloudinary'),
        };
    }

    public static function maskSensitiveData(string $data, int $visibleChars = 4): string
    {
        if (strlen($data) <= $visibleChars) {
            return str_repeat('*', strlen($data));
        }

        return substr($data, 0, $visibleChars) . str_repeat('*', min(8, strlen($data) - $visibleChars));
    }

    public static function sanitizeParams(array $params): array
    {
        $exactKeys = ['key', 'secret', 'token', 'password', 'authorization', 'cookie'];

        $compoundKeys = [
            'signature',
            'api_key',
            'api_secret',
            'access_token',
            'x-api-key',
            'set-cookie',
        ];

        $sanitized = $params;

        foreach ($params as $key => $value) {
            $lowerKey = strtolower($key);

            $isExact = in_array($lowerKey, $exactKeys, true);
            $isCompound = !$isExact && array_filter($compoundKeys, fn($k) => str_contains($lowerKey, $k));

            if ($isExact || $isCompound) {
                $sanitized[$key] = self::maskSensitiveData((string) $value);
            }
        }

        return $sanitized;
    }
}
