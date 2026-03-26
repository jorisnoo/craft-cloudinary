<?php

namespace Noo\CraftCloudinary\controllers;

use craft\helpers\FileHelper;
use craft\web\Controller;
use Noo\CraftCloudinary\Cloudinary;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class ThumbnailsController extends Controller
{
    public function actionServe(): Response
    {
        $assetId = (int) $this->request->getRequiredQueryParam('assetId');
        $width = (int) $this->request->getRequiredQueryParam('w');
        $height = (int) $this->request->getRequiredQueryParam('h');

        $cache = Cloudinary::getInstance()->thumbnailCache;
        $cachedPath = $cache->get($assetId, $width, $height);

        if ($cachedPath === null) {
            throw new NotFoundHttpException('Thumbnail not cached');
        }

        $mimeType = FileHelper::getMimeTypeByExtension($cachedPath) ?? 'image/jpeg';

        $this->response->headers->set('Cache-Control', 'public, max-age=604800');
        $this->response->headers->set('ETag', '"' . md5_file($cachedPath) . '"');

        return $this->response->sendFile($cachedPath, null, [
            'mimeType' => $mimeType,
            'inline' => true,
        ]);
    }
}
