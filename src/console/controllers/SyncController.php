<?php

namespace Noo\CraftCloudinary\console\controllers;

use Craft;
use Noo\CraftCloudinary\Cloudinary;
use Noo\CraftCloudinary\fs\CloudinaryFs;
use yii\console\Controller;
use yii\console\ExitCode;

class SyncController extends Controller
{
    public bool $deleteMissingAssets = true;

    public function options($actionID): array
    {
        return [
            ...parent::options($actionID),
            'deleteMissingAssets',
        ];
    }

    public function actionIndex(): int
    {
        $volumes = $this->getCloudinaryVolumes();

        if (empty($volumes)) {
            $this->stderr("No Cloudinary volumes found.\n");
            return ExitCode::DATAERR;
        }

        Craft::$app->controllerNamespace = 'craft\console\controllers';

        Craft::$app->runAction('index-assets/cleanup');

        foreach ($volumes as $volume) {
            $this->stdout("Syncing volume \"{$volume->name}\"...\n");

            Craft::$app->runAction('index-assets/one', [
                $volume->handle,
                '--delete-missing-assets' => $this->deleteMissingAssets ? '1' : '0',
                '--interactive' => '0',
            ]);
        }

        return ExitCode::OK;
    }

    public function actionReconcile(): int
    {
        $volumes = $this->getCloudinaryVolumes();

        if (empty($volumes)) {
            $this->stderr("No Cloudinary volumes found.\n");
            return ExitCode::DATAERR;
        }

        $reconciler = Cloudinary::getInstance()->syncReconciler;

        foreach ($volumes as $volume) {
            $this->stdout("Reconciling volume \"{$volume->name}\"...\n");

            $stats = $reconciler->reconcile($volume->id);

            $this->stdout("  Created: {$stats['created']}\n");
            $this->stdout("  Deleted: {$stats['deleted']}\n");
            $this->stdout("  Updated: {$stats['updated']}\n");
            $this->stdout("  Unchanged: {$stats['unchanged']}\n");
        }

        $this->stdout("Reconciliation complete.\n");

        return ExitCode::OK;
    }

    private function getCloudinaryVolumes(): array
    {
        return array_filter(
            Craft::$app->getVolumes()->getAllVolumes(),
            fn($v) => $v->getFs() instanceof CloudinaryFs,
        );
    }
}
