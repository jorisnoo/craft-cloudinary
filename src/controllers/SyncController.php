<?php

namespace Noo\CraftCloudinary\controllers;

use Craft;
use craft\helpers\Queue;
use craft\web\Controller;
use Noo\CraftCloudinary\fs\CloudinaryFs;
use Noo\CraftCloudinary\jobs\SyncVolume;
use yii\web\Response;

class SyncController extends Controller
{
    public function actionIndex(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('utility:cloudinary');

        $queued = 0;

        foreach (Craft::$app->getVolumes()->getAllVolumes() as $volume) {
            if (!$volume->getFs() instanceof CloudinaryFs) {
                continue;
            }

            Queue::push(new SyncVolume($volume->id));
            $queued++;
        }

        if ($queued === 0) {
            return $this->asFailure(Craft::t('cloudinary', 'No Cloudinary volumes to sync.'));
        }

        return $this->asSuccess(Craft::t('cloudinary', 'Cloudinary sync queued.'));
    }
}
