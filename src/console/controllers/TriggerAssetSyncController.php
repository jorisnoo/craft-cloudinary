<?php

namespace Noo\CraftCloudinary\console\controllers;

use Craft;
use craft\helpers\Queue;
use Noo\CraftCloudinary\fs\CloudinaryFs;
use Noo\CraftCloudinary\jobs\SyncCloudinaryAssetVolume;
use yii\console\Controller;
use yii\console\ExitCode;

class TriggerAssetSyncController extends Controller
{
    public function actionSync(?int $volumeId = null): int
    {
        $volumes = $volumeId
            ? [$this->resolveVolume($volumeId)]
            : $this->getCloudinaryVolumes();

        if (empty($volumes)) {
            $this->stderr("No Cloudinary volumes found.\n");
            return ExitCode::DATAERR;
        }

        foreach ($volumes as $volume) {
            Queue::push(new SyncCloudinaryAssetVolume($volume->handle));
            $this->stdout("Queued sync for volume \"{$volume->name}\".\n");
        }

        return ExitCode::OK;
    }

    private function resolveVolume(int $volumeId): \craft\models\Volume
    {
        $volume = Craft::$app->getVolumes()->getVolumeById($volumeId);

        if (!$volume) {
            throw new \yii\console\Exception("Volume not found: {$volumeId}");
        }

        if (!$volume->getFs() instanceof CloudinaryFs) {
            throw new \yii\console\Exception("Volume \"{$volume->name}\" is not a Cloudinary volume.");
        }

        return $volume;
    }

    private function getCloudinaryVolumes(): array
    {
        return array_filter(
            Craft::$app->getVolumes()->getAllVolumes(),
            fn($v) => $v->getFs() instanceof CloudinaryFs,
        );
    }
}
