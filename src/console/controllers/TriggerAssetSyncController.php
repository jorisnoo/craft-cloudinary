<?php

namespace jorisnoo\craftcloudinary\console\controllers;

use Cloudinary\Api\Exception\NotFound;
use Craft;
use craft\helpers\Queue;
use jorisnoo\craftcloudinary\jobs\SyncCloudinaryAssetVolume;
use yii\console\Controller;
use yii\di\Instance;

class TriggerAssetSyncController extends Controller
{
    public function actionSync($volumeId): void
    {
        $volume = Craft::$app->getVolumes()->getVolumeById($volumeId);

        if (!$volume) {
            throw new NotFound('Volume not found');
        }

        // Remove all previous jobs on the dedicated queue
        /* @var \yii\queue\Queue $queue */
        // $queue = Instance::ensure('cloudinaryQueue', \yii\queue\Queue::class);
        // $queue->releaseAll();

        // Push the job to the queue
        Queue::push(
            job: new SyncCloudinaryAssetVolume($volume->handle),
            // queue: $queue,
        );
    }
}
