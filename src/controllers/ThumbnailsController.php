<?php

namespace Noo\CraftCloudinary\controllers;

use craft\elements\Asset;
use craft\helpers\FileHelper;
use craft\web\Controller;
use Noo\CraftCloudinary\Cloudinary;
use Noo\CraftCloudinary\fs\CloudinaryFs;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class ThumbnailsController extends Controller
{
    public function actionServe(): Response
    {
        $assetId = (int) $this->request->getRequiredQueryParam('assetId');
        $width = (int) $this->request->getRequiredQueryParam('w');
        $height = (int) $this->request->getRequiredQueryParam('h');

        $asset = Asset::find()->id($assetId)->one();

        if (!$asset) {
            throw new NotFoundHttpException('Asset not found');
        }

        $volume = $asset->getVolume();
        $fs = $volume->getFs();
        $transformFs = $volume->getTransformFs();

        if (!$fs instanceof CloudinaryFs && !$transformFs instanceof CloudinaryFs) {
            throw new NotFoundHttpException('Asset is not on a Cloudinary volume');
        }

        $cache = Cloudinary::getInstance()->thumbnailCache;
        $cachedPath = $cache->get($assetId, $width, $height);

        if ($cachedPath !== null) {
            return $this->serveCachedFile($cachedPath);
        }

        $cloudinaryUrl = $asset->getCloudinaryUrl([
            'width' => $width,
            'height' => $height,
            'crop' => 'fill',
            'gravity' => 'auto',
            'fetch_format' => 'auto',
            'quality' => 'auto',
        ]);

        $contents = @file_get_contents($cloudinaryUrl);

        if ($contents === false) {
            Cloudinary::log("Failed to fetch thumbnail from Cloudinary: {$cloudinaryUrl}", 'error');
            throw new NotFoundHttpException('Failed to fetch thumbnail');
        }

        $extension = $this->detectExtension($contents);
        $cachedPath = $cache->put($assetId, $width, $height, $contents, $extension);

        return $this->serveCachedFile($cachedPath);
    }

    private function serveCachedFile(string $path): Response
    {
        $mimeType = FileHelper::getMimeTypeByExtension($path) ?? 'image/jpeg';

        $this->response->headers->set('Cache-Control', 'public, max-age=604800');
        $this->response->headers->set('ETag', '"' . md5_file($path) . '"');

        return $this->response->sendFile($path, null, [
            'mimeType' => $mimeType,
            'inline' => true,
        ]);
    }

    private function detectExtension(string $contents): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($contents);

        return match ($mimeType) {
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            'image/svg+xml' => 'svg',
            default => 'jpg',
        };
    }
}
