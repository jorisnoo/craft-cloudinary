<?php

namespace Noo\CraftCloudinary\actions;

use Craft;
use Noo\CraftCloudinary\Cloudinary;

class AssetChangeDisplayNameAction extends BaseCloudinaryAction
{
    /**
     * @param array $resources
     */
    public function change(array $resources): void
    {
        foreach ($resources as $resource) {
            $resourceType = $resource['resource_type'];
            $displayName = $resource['new_display_name'];
            $publicId = $resource['public_id'];

            $assetFolder = $this->relativeAssetFolder($resource['asset_folder'] ?? '');

            if ($assetFolder === null) {
                $this->logSkippedOutsideSubpath("display name change of '{$publicId}'", $resource['asset_folder'] ?? '');
                continue;
            }

            $asset = $this->queryAsset($publicId, $assetFolder, $resourceType);

            if ($asset) {
                $asset->title = $displayName;

                Craft::$app->getElements()->saveElement($asset);

                Cloudinary::log("Changed asset title - Asset: {$asset->id}, Title: {$displayName}, Public ID: {$publicId}");
            } else {
                Cloudinary::log("Could not find asset to rename - Public ID: {$publicId}, Type: {$resourceType}", 'warning');
            }
        }
    }
}
