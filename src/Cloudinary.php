<?php

namespace Noo\CraftCloudinary;

use Craft;
use craft\base\Event;
use craft\base\Plugin;
use craft\console\Controller;
use craft\elements\Asset;
use craft\events\DefineBehaviorsEvent;
use craft\events\DefineConsoleActionsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Fs;
use craft\services\ImageTransforms;
use Noo\CraftCloudinary\behaviors\CloudinaryUrlBehavior;
use Noo\CraftCloudinary\console\controllers\RemovePathsFromPublicIdsController;
use Noo\CraftCloudinary\console\controllers\TriggerAssetSyncController;
use Noo\CraftCloudinary\fs\CloudinaryFs;
use Noo\CraftCloudinary\imagetransforms\CloudinaryTransformer;
use yii\log\FileTarget;

class Cloudinary extends Plugin
{
    public string $schemaVersion = '1.0.0';

    public function init(): void
    {
        parent::init();

        Craft::$app->onInit(function() {
            $this->registerFilesystemTypes();
            $this->registerImageTransformers();
            $this->defineBehaviors();
            $this->registerConsoleCommands();
            $this->defineLogTarget();
        });
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

    protected function defineLogTarget(): void
    {
        // Create a new log target with daily rotation
        $logTarget = new FileTarget();
        $logTarget->logFile = Craft::getAlias('@storage/logs/cloudinary-' . date('Y-m-d') . '.log');
        $logTarget->levels = ['error', 'warning', 'info'];
        $logTarget->categories = ['cloudinary'];
        $logTarget->maxFileSize = 10240; // 10MB
        $logTarget->maxLogFiles = 30; // Keep last 30 days

        // Disable automatic logging of $_SERVER, $_GET, $_POST, etc. to prevent sensitive data leakage
        $logTarget->logVars = [];

        // Add the log target to the log component
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
