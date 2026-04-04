<?php

namespace Noo\CraftCloudinary;

use Craft;
use craft\base\Event;
use craft\base\Plugin;
use craft\console\Controller;
use craft\elements\Asset;
use craft\events\DefineAssetThumbUrlEvent;
use craft\events\DefineBehaviorsEvent;
use craft\events\DefineConsoleActionsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Assets;
use craft\services\Fs;
use craft\services\ImageTransforms;
use craft\services\Utilities;
use Noo\CraftCloudinary\behaviors\CloudinaryUrlBehavior;
use Noo\CraftCloudinary\console\controllers\RemovePathsFromPublicIdsController;
use Noo\CraftCloudinary\console\controllers\SyncController;
use Noo\CraftCloudinary\fs\CloudinaryFs;
use Noo\CraftCloudinary\imagetransforms\CloudinaryTransformer;
use Noo\CraftCloudinary\listeners\AssetEventListener;
use Noo\CraftCloudinary\services\CloudinaryApi;
use Noo\CraftCloudinary\services\SyncGuard;
use Noo\CraftCloudinary\services\SyncReconciler;
use Noo\CraftCloudinary\utilities\CloudinaryUtility;
use yii\log\FileTarget;

/**
 * @property CloudinaryApi $cloudinaryApi
 * @property SyncGuard $syncGuard
 * @property SyncReconciler $syncReconciler
 */
class Cloudinary extends Plugin
{
    public string $schemaVersion = '2.1.0';

    public function init(): void
    {
        parent::init();

        $this->setComponents([
            'cloudinaryApi' => CloudinaryApi::class,
            'syncGuard' => SyncGuard::class,
            'syncReconciler' => SyncReconciler::class,
        ]);

        Craft::$app->onInit(function() {
            $this->registerFilesystemTypes();
            $this->registerImageTransformers();
            $this->defineBehaviors();
            $this->registerConsoleCommands();
            $this->defineLogTarget();
            $this->registerThumbnails();

            AssetEventListener::register();

            if (Craft::$app->getRequest()->getIsCpRequest()) {
                $this->registerUtilities();
            }
        });
    }

    public function registerConsoleCommands(): void
    {
        // php craft cloudinary/sync
        Event::on(
            SyncController::class,
            Controller::EVENT_DEFINE_ACTIONS,
            function(DefineConsoleActionsEvent $event) {
                $event->actions['index'] = [
                    'helpSummary' => 'Sync all Cloudinary asset volumes via Flysystem listing',
                    'action' => function() {
                        $controller = Craft::$app->controller;
                        return $controller->actionIndex();
                    },
                ];
                $event->actions['reconcile'] = [
                    'helpSummary' => 'Reconcile Craft assets with Cloudinary via Search API',
                    'action' => function() {
                        $controller = Craft::$app->controller;
                        return $controller->actionReconcile();
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
    }

    private function registerUtilities(): void
    {
        Event::on(Utilities::class, Utilities::EVENT_REGISTER_UTILITIES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = CloudinaryUtility::class;
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

    private function registerThumbnails(): void
    {
        Event::on(Assets::class, Assets::EVENT_DEFINE_THUMB_URL, function(DefineAssetThumbUrlEvent $event) {
            $asset = $event->asset;
            $volume = $asset->getVolume();
            $fs = $volume->getFs();
            $transformFs = $volume->getTransformFs();

            if (!$fs instanceof CloudinaryFs && !$transformFs instanceof CloudinaryFs) {
                return;
            }

            $event->url = $asset->getCloudinaryUrl([
                'width' => $event->width,
                'height' => $event->height,
                'crop' => 'fill',
                'gravity' => 'auto',
                'fetch_format' => 'auto',
                'quality' => 'auto',
            ]);
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
