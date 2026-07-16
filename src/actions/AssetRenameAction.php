<?php

namespace Noo\CraftCloudinary\actions;

use Cloudinary\Asset\AssetType;
use Craft;

class AssetRenameAction extends BaseCloudinaryAction
{
    public function rename(
        string $fromPublicId,
        string $toPublicId,
        string $assetFolder,
        string $resourceType,
    ): void {
        // get the asset folder path relative to the volume subpath
        $path = $this->relativeAssetFolder($assetFolder);

        if ($path === null) {
            $this->logSkippedOutsideSubpath("rename of '{$fromPublicId}'", $assetFolder);
            return;
        }

        // Remove path from the new public_id
        $this->removePathFromPublicId($toPublicId, $resourceType);

        // public ids should never contain paths, but anyway, remove them
        $fromFilename = basename($fromPublicId);
        $toFilename = basename($toPublicId);

        if ($fromFilename === $toFilename) {
            return;
        }

        $asset = $this->queryAsset($fromPublicId, $path, $resourceType);

        if ($asset === null) {
            return;
        }

        if ($resourceType === AssetType::RAW) {
            $asset->filename = $toFilename;
        } else {
            $extension = pathinfo($asset->filename, PATHINFO_EXTENSION);
            $asset->filename = "$toFilename.$extension";
        }

        Craft::$app->getElements()->saveElement($asset);
    }
}
