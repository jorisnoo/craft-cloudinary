<?php

namespace Noo\CraftCloudinary\actions;

use Cloudinary\Asset\AssetType;
use Craft;
use craft\elements\Asset;
use Noo\CraftCloudinary\actions\BaseCloudinaryAction;

class AssetDeleteAction extends BaseCloudinaryAction
{
    /**
     * @param array $resources
     */
    public function delete(array $resources): void
    {
        // Loop through all assets to be deleted
        foreach ($resources as $resource) {

            $resourceType = $resource['resource_type'];
            $publicId = $resource['public_id'];
            $folderPath = $this->formatPath($resource['asset_folder']);

            $asset = $this->queryAsset($publicId, $folderPath, $resourceType);

            if ($asset) {
                Craft::$app->getElements()->deleteElement($asset);
            }
        }
    }
}
