<?php

namespace Noo\CraftCloudinary\actions;

use Craft;
use Noo\CraftCloudinary\Cloudinary;

class AssetMoveAction extends BaseCloudinaryAction
{
    /**
     * @param array $resources
     */
    public function move(array $resources): void
    {
        foreach ($resources as $publicId => $resource) {
            $resourceType = $resource['resource_type'];
            $fromFolder = $this->relativeAssetFolder($resource['from_asset_folder'] ?? '');
            $toFolder = $this->relativeAssetFolder($resource['to_asset_folder'] ?? '');

            if ($fromFolder === null && $toFolder === null) {
                $this->logSkippedOutsideSubpath("move of '{$publicId}'", $resource['from_asset_folder'] ?? '');
                continue;
            }

            if ($toFolder === null) {
                // The asset was moved out of the volume subpath, so it no
                // longer belongs to this volume.
                $this->deleteMovedOutAsset($publicId, $fromFolder, $resourceType);
                continue;
            }

            if ($fromFolder === null) {
                // The asset was moved into the volume subpath. The move webhook
                // doesn't carry enough metadata to create it, so leave it to a sync.
                Cloudinary::log(
                    "Asset '{$publicId}' was moved into the volume subpath from '" . ($resource['from_asset_folder'] ?? '') . "' - run a sync to import it",
                    'warning',
                );
                continue;
            }

            $asset = $this->queryAsset($publicId, $fromFolder, $resourceType);

            if ($asset === null) {
                continue;
            }

            $targetFolder = (new FolderCreateAction($this->volumeId))->firstOrCreate($toFolder);

            if ($asset->folderId === (int) $targetFolder->id) {
                continue;
            }

            $asset->folderId = $targetFolder->id;

            Craft::$app->getElements()->saveElement($asset);
        }
    }

    protected function deleteMovedOutAsset(string $publicId, string $fromFolder, string $resourceType): void
    {
        $asset = $this->queryAsset($publicId, $fromFolder, $resourceType);

        if ($asset === null) {
            return;
        }

        // The file still exists in Cloudinary, just outside this volume.
        $asset->keepFileOnDelete = true;
        Craft::$app->getElements()->deleteElement($asset);
    }
}
