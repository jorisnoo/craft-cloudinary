<?php

namespace Noo\CraftCloudinary\console\controllers;

use Cloudinary\Api\Exception\NotFound;
use Craft;
use craft\helpers\Queue;
use Noo\CraftCloudinary\jobs\SyncCloudinaryAssetVolume;
use yii\console\Controller;

class TriggerAssetSyncController extends Controller
{
    public function actionSync($volumeId): void
    {
        $volume = Craft::$app->getVolumes()->getVolumeById($volumeId);

        if (!$volume) {
            throw new NotFound('Volume not found');
        }

        Queue::push(
            job: new SyncCloudinaryAssetVolume($volume->handle),
        );
    }
}
