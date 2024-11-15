<?php

namespace jorisnoo\craftcloudinary\actions;

use Cloudinary\Asset\AssetType;
use Craft;
use craft\elements\Asset;
use jorisnoo\craftcloudinary\actions\BaseCloudinaryAction;

class AssetRenameAction extends BaseCloudinaryAction
{
    public function rename(
        string $fromPublicId,
        string $toPublicId,
        string $assetFolder,
        string $resourceType,
    ): void
    {
        // Remove path from the new public_id
        $this->removePathFromPublicId($toPublicId, $resourceType);

        // get the asset folder path
        $path = $this->formatPath($assetFolder);

        // public ids should never contain paths, but anyway, remove them
        $fromFilename = basename($fromPublicId);
        $toFilename = basename($toPublicId);

        if ($fromFilename === $toFilename) {
            return;
        }

        $asset = $this->queryAsset($fromPublicId, $path, $resourceType);

        // If an asset is found, update the filename and save it
        if ($asset) {

            if ($resourceType === AssetType::RAW) {
                $asset->filename = $toFilename;
            } else {
                $extension = pathinfo($asset->filename, PATHINFO_EXTENSION);
                $asset->filename = "$toFilename.$extension";
            }

            Craft::$app->getElements()->saveElement($asset);
        }
    }
}
