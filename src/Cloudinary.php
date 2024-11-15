<?php

namespace jorisnoo\craftcloudinary;

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
use jorisnoo\craftcloudinary\behaviors\CloudinaryUrlBehavior;
use jorisnoo\craftcloudinary\console\controllers\RemovePathsFromPublicIdsController;
use jorisnoo\craftcloudinary\console\controllers\TriggerAssetSyncController;
use jorisnoo\craftcloudinary\fs\CloudinaryFs;
use jorisnoo\craftcloudinary\imagetransforms\CloudinaryTransformer;
use yii\log\FileTarget;

class Cloudinary extends Plugin
{
    public string $schemaVersion = '1.0.0';

    public function init(): void
    {
        parent::init();

        Craft::$app->onInit(function () {
            $this->registerFilesystemTypes();
            $this->registerImageTransformers();
            $this->defineBehaviors();
            $this->registerConsoleCommands();
            $this->defineLogTarget();
        });
    }

    public function registerConsoleCommands(): void
    {
        // Add the console command to manually trigger the sync job
        // php craft _cloudinary/trigger-asset-sync/sync
        Event::on(
            TriggerAssetSyncController::class,
            Controller::EVENT_DEFINE_ACTIONS,
            function (DefineConsoleActionsEvent $event) {
                $event->actions['sync'] = [
                    'helpSummary' => 'Trigger a sync of all asset volumes with Cloudinary',
                    'action' => function ($params) {
                        $controller = Craft::$app->controller;
                        $controller->actionSync();
                    }
                ];
            }
        );

        // php craft _cloudinary/remove-paths-from-public-ids/scan 1
        Event::on(
            RemovePathsFromPublicIdsController::class,
            Controller::EVENT_DEFINE_ACTIONS,
            function (DefineConsoleActionsEvent $event) {
                $event->actions['remove-paths-from-public-ids'] = [
                    'helpSummary' => 'Scan all Cloudinary assets and remove paths from their public ids',
                    'action' => function ($params) {
                        $controller = Craft::$app->controller;
                        $controller->actionScan($params);
                    }
                ];
            }
        );
    }

    private function registerFilesystemTypes(): void
    {
        Event::on(Fs::class, Fs::EVENT_REGISTER_FILESYSTEM_TYPES, function (RegisterComponentTypesEvent $event) {
            $event->types[] = CloudinaryFs::class;
        });
    }

    private function registerImageTransformers(): void
    {
        Event::on(ImageTransforms::class, ImageTransforms::EVENT_REGISTER_IMAGE_TRANSFORMERS, function (RegisterComponentTypesEvent $event) {
            $event->types[] = CloudinaryTransformer::class;
        });
    }

    private function defineBehaviors(): void
    {
        Event::on(Asset::class, Asset::EVENT_DEFINE_BEHAVIORS, function (DefineBehaviorsEvent $event) {
            $volume = $event->sender->getVolume();
            $fs = $volume->getFs();
            $transformFs = $volume->getTransformFs();

            if ($fs instanceof CloudinaryFs || $transformFs instanceof CloudinaryFs) {
                $event->behaviors['cloudinary:url'] = CloudinaryUrlBehavior::class;
            }
        });
    }

    protected function defineLogTarget()
    {
        // Create a new log target
        $logTarget = new FileTarget();
        $logTarget->logFile = Craft::getAlias('@storage/logs/cloudinary.log'); // Path to your log file
        $logTarget->levels = ['error', 'warning', 'info']; // Log levels you want to capture
        $logTarget->categories = ['cloudinary'];

        // Add the log target to the log component
        Craft::$app->log->targets[] = $logTarget;
    }

    public static function log($message, $level = 'info'): void
    {
        Craft::info($message, 'cloudinary');
    }
}
