<?php

namespace Noo\CraftCloudinary\controllers;

use craft\web\Controller;
use Noo\CraftCloudinary\Cloudinary;
use yii\web\Response;

class CacheController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requireCpRequest();
        $this->requireAdmin();

        return true;
    }

    public function actionClear(): Response
    {
        $plugin = Cloudinary::getInstance();

        $plugin->thumbnailCache->invalidateAll();
        $plugin->activityLog->log('cache:clear', 'Thumbnail cache cleared');

        $this->setSuccessFlash('Cloudinary thumbnail cache cleared.');

        return $this->redirectToPostedUrl();
    }

    public function actionCleanup(): Response
    {
        $plugin = Cloudinary::getInstance();

        $removed = $plugin->thumbnailCache->cleanup();
        $plugin->activityLog->log('cache:cleanup', "Cleaned up {$removed} expired thumbnail(s)");

        $this->setSuccessFlash("Removed {$removed} expired thumbnail(s).");

        return $this->redirectToPostedUrl();
    }
}
