<?php

namespace jorisnoo\craftcloudinary\actions;

use Cloudinary\Asset\AssetType;
use Craft;
use craft\elements\Asset;
use jorisnoo\craftcloudinary\actions\BaseCloudinaryAction;
use jorisnoo\craftcloudinary\Cloudinary;

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

            $assetFolder = $this->formatPath($resource['asset_folder']);

            $asset = $this->queryAsset($publicId, $assetFolder, $resourceType);

            if($asset) {
                $asset->title = $displayName;

                Craft::$app->getElements()->saveElement($asset);

                Cloudinary::log("Changed asset title");
                Cloudinary::log([
                    'asset' => $asset->id,
                    'title' => $displayName,
                    'publicId' => $publicId,
                    'resourceType' => $resourceType,
                ]);
            } else {
                Cloudinary::log("Could not find asset to rename");
                Cloudinary::log([
                    'publicId' => $publicId,
                    'resourceType' => $resourceType,
                ]);
            }
        }
    }
}
