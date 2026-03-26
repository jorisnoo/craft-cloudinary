<?php

namespace Noo\CraftCloudinary\actions;

use Craft;
use Noo\CraftCloudinary\Cloudinary;

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
            $folderPath = $this->formatPath($resource['asset_folder']);

            $asset = $this->queryAsset($publicId, $folderPath, $resourceType);

            if ($asset) {
                if (Cloudinary::getInstance()->getSettings()->enableThumbnailCache) {
                    Cloudinary::getInstance()->thumbnailCache->invalidateAsset($asset->id);
                }

                Craft::$app->getElements()->deleteElement($asset);
            }
        }
    }
}
