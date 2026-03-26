<?php

namespace Noo\CraftCloudinary\console\controllers;

use Noo\CraftCloudinary\Cloudinary;
use yii\console\Controller;
use yii\console\ExitCode;

class ThumbnailCacheController extends Controller
{
    public function actionClear(): int
    {
        Cloudinary::getInstance()->thumbnailCache->invalidateAll();
        $this->stdout("Cloudinary thumbnail cache cleared.\n");

        return ExitCode::OK;
    }

    public function actionCleanup(): int
    {
        $removed = Cloudinary::getInstance()->thumbnailCache->cleanup();
        $this->stdout("Removed {$removed} expired thumbnail(s).\n");

        return ExitCode::OK;
    }
}
