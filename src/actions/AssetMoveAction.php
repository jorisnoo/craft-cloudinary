<?php

namespace Noo\CraftCloudinary\actions;

use Craft;

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
}
