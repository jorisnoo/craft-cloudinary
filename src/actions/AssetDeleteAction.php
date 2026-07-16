<?php

namespace Noo\CraftCloudinary\actions;

use Craft;

class AssetDeleteAction extends BaseCloudinaryAction
{
    /**
     * @param array $resources
     */
    public function delete(array $resources): void
    {
        foreach ($resources as $resource) {
            $resourceType = $resource['resource_type'];
            $publicId = $resource['public_id'];
            $folderPath = $this->relativeAssetFolder($resource['asset_folder'] ?? '');

            if ($folderPath === null) {
                $this->logSkippedOutsideSubpath("delete of '{$publicId}'", $resource['asset_folder'] ?? '');
                continue;
            }

            $asset = $this->queryAsset($publicId, $folderPath, $resourceType);

            if ($asset) {
                Craft::$app->getElements()->deleteElement($asset);
            }
        }
    }
}
