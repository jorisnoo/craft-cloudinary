<?php

namespace jorisnoo\craftcloudinary\actions;

use Cloudinary\Asset\AssetType;
use Craft;
use craft\elements\Asset;
use jorisnoo\craftcloudinary\actions\BaseCloudinaryAction;

class AssetChangeDisplayNameAction extends BaseCloudinaryAction
{
    /**
     * @param array $resources
     */
    public function change(array $resources): void
    {
        foreach ($resources as $publicId => $resource) {
            $resourceType = $resource['resource_type'];
            $displayName = $resource['new_display_name'];

            $assetFolder = $this->formatPath($resource['asset_folder']);
            $filename = $this->formatFilename($publicId, $resourceType);

            $asset = $this->queryAsset($filename, $assetFolder, $resourceType);

            if($asset) {
                $asset->title = $displayName;

                Craft::$app->getElements()->saveElement($asset);
            }
        }
    }
}
