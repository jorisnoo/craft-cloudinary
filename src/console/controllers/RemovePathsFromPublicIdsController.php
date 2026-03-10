<?php

namespace Noo\CraftCloudinary\console\controllers;

use Cloudinary\Api\Exception\NotFound;
use Cloudinary\Asset\AssetType;
use Craft;
use craft\helpers\Queue;
use League\Flysystem\FileAttributes;
use Noo\CraftCloudinary\Cloudinary;
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

        $contentList = $volume
            ->getFs()
            ->getCloudinaryFilesystem()
            ->listContents("", true);

        collect($contentList)
            // get only files
            ->filter(fn($item) => $item instanceof FileAttributes)
            // get only their public ids and resource type
            ->map(function($item) {
                $mimeType = $item['mimeType'];

                $resourceType = match (true) {
                    str_starts_with($mimeType, "image/"), $mimeType === "application/pdf" => AssetType::IMAGE,
                    str_starts_with($mimeType, "video/"), str_starts_with($mimeType, "audio/") => AssetType::VIDEO,
                    default => AssetType::RAW,
                };

                return [
                    "resource_type" => $resourceType,
                    "public_id" => pathinfo($item->path(), PATHINFO_FILENAME),
                ];
            })
            // get only the ones where the public_id contains a path
            ->filter(fn($item) => $item["public_id"] !== basename($item["public_id"]))
            ->each(function($item) use ($volumeId) {
                Cloudinary::log("Dispatching job to remove path from public_id {$item["public_id"]}");
                Queue::push(new RemovePathFromCloudinaryPublicId($volumeId, $item["public_id"], $item["resource_type"]));
            });
    }
}
