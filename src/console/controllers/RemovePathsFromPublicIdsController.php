<?php

namespace Noo\CraftCloudinary\console\controllers;

use Cloudinary\Api\Exception\NotFound;
use Craft;
use craft\helpers\Queue;
use Noo\CraftCloudinary\Cloudinary;
use Noo\CraftCloudinary\fs\CloudinaryFs;
use Noo\CraftCloudinary\helpers\CloudinaryAssetSearch;
use Noo\CraftCloudinary\jobs\RemovePathFromCloudinaryPublicId;
use yii\console\Controller;

class RemovePathsFromPublicIdsController extends Controller
{
    public function actionScan($volumeId): void
    {
        $volume = Craft::$app->getVolumes()->getVolumeById($volumeId);

        if (!$volume) {
            throw new NotFound('Volume not found');
        }

        $fs = $volume->getFs();

        if (!$fs instanceof CloudinaryFs) {
            throw new \InvalidArgumentException("Volume {$volumeId} does not use a Cloudinary filesystem");
        }

        $resources = CloudinaryAssetSearch::resources(
            $fs->getClient(),
            $volume->getSubpath(false),
            ['public_id', 'resource_type'],
        );

        foreach ($resources as $resource) {
            $publicId = $resource['public_id'];

            if ($publicId === basename($publicId)) {
                continue;
            }

            Cloudinary::log("Dispatching job to remove path from public_id {$publicId}");
            Queue::push(new RemovePathFromCloudinaryPublicId(
                $volumeId,
                $publicId,
                $resource['resource_type'],
            ));
        }
    }
}
