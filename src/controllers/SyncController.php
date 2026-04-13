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

        $force = (bool) Craft::$app->getRequest()->getBodyParam('force', false);

        $queued = 0;

        foreach (Craft::$app->getVolumes()->getAllVolumes() as $volume) {
            if (!$volume->getFs() instanceof CloudinaryFs) {
                continue;
            }

            Queue::push(new SyncVolume($volume->id, $force));
            $queued++;
        }

        if ($queued === 0) {
            return $this->asFailure(Craft::t('cloudinary', 'No Cloudinary volumes to sync.'));
        }

        $message = $force
            ? Craft::t('cloudinary', 'Cloudinary sync queued (forced).')
            : Craft::t('cloudinary', 'Cloudinary sync queued.');

        return $this->asSuccess($message);
    }
}
