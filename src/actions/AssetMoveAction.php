<?php

namespace jorisnoo\craftcloudinary\actions;

use Cloudinary\Asset\AssetType;
use Craft;
use craft\elements\Asset;
use jorisnoo\craftcloudinary\actions\BaseCloudinaryAction;
use jorisnoo\craftcloudinary\actions\FolderCreateAction;

class AssetMoveAction extends BaseCloudinaryAction
{
    /**
     * @param array $resources
     */
    public function move(array $resources): void
    {
        foreach ($resources as $publicId => $resource) {
            $resourceType = $resource['resource_type'];
            $fromFolder = $this->formatPath($resource['from_asset_folder']);
            $toFolder = $this->formatPath($resource['to_asset_folder']);

            // Get the asset
            $asset = $this->queryAsset($publicId, $fromFolder, $resourceType);

            if($asset === null) {
                return;
            }

            // Get the new folder
            $targetFolder = (new FolderCreateAction($this->volumeId))->firstOrCreate($toFolder);

            $asset->folderId = $targetFolder->id;

            Craft::$app->getElements()->saveElement($asset);
        }
    }
}
