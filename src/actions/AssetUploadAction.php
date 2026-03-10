<?php

namespace Noo\CraftCloudinary\actions;

use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Asset;
use craft\helpers\Assets;
use Noo\CraftCloudinary\Cloudinary;

class AssetUploadAction extends BaseCloudinaryAction
{
    public function upload(
        string $publicId,
        string $assetFolder,
        string $resourceType,
        string $displayName,
        int $size,
        ?int $width,
        ?int $height,
        ?string $format,
        ?string $createdAt,
    ): void
    {
        Cloudinary::log("=== AssetUploadAction Started ===");
        Cloudinary::log("Upload params - Public ID: {$publicId}, Folder: {$assetFolder}, Type: {$resourceType}, Display Name: {$displayName}");
        Cloudinary::log("Asset details - Size: {$size} bytes, Width: {$width}, Height: {$height}, Format: {$format}, Created: {$createdAt}");

        $this->removePathFromPublicId($publicId, $resourceType);

        // First, get or create the asset folder
        Cloudinary::log("Getting or creating asset folder: {$assetFolder}");
        $folder = (new FolderCreateAction($this->volumeId))->firstOrCreate($assetFolder);
        Cloudinary::log("Folder retrieved/created - Folder ID: {$folder->id}, Folder path: {$folder->path}");

        // Prepare the filename
        $filename = $this->formatFilename($publicId, $resourceType, $format);
        Cloudinary::log("Formatted filename: {$filename}");

        // Check if the asset already exists
        Cloudinary::log("Checking if asset already exists - Volume: {$this->volumeId}, Folder: {$folder->id}, Filename: {$filename}");
        $existingAssetQuery = (new Query())
            ->from(['assets' => Table::ASSETS])
            ->innerJoin(['elements' => Table::ELEMENTS], '[[elements.id]] = [[assets.id]]')
            ->where([
                'assets.volumeId' => $this->volumeId,
                'assets.folderId' => $folder->id,
                'assets.filename' => $filename,
                'elements.dateDeleted' => null,
            ]);

        if ($existingAssetQuery->exists()) {
            Cloudinary::log("Asset already exists, skipping creation");
            Cloudinary::log("=== AssetUploadAction Completed (existing asset) ===");
            return;
        }

        Cloudinary::log("Asset does not exist, proceeding with creation");

        // Otherwise, store it
        $kind = Assets::getFileKindByExtension($filename);
        Cloudinary::log("Asset kind determined: {$kind}");

        $asset = new Asset([
            'volumeId' => $this->volumeId,
            'folderId' => $folder->id,
            'filename' => $filename,
            'title' => $displayName,
            'kind' => $kind,
            'size' => $size,
            'dateCreated' => $createdAt,
            'dateModified' => $createdAt,
        ]);

        if ($kind === Asset::KIND_IMAGE) {
            $asset->width = $width;
            $asset->height = $height;
            Cloudinary::log("Image dimensions set - Width: {$width}, Height: {$height}");
        }

        $asset->setScenario(Asset::SCENARIO_INDEX);
        Cloudinary::log("Asset scenario set to: " . Asset::SCENARIO_INDEX);

        Cloudinary::log("Attempting to save asset element...");
        $saved = Craft::$app->getElements()->saveElement($asset);

        if ($saved) {
            Cloudinary::log("Asset saved successfully - Asset ID: {$asset->id}");
        } else {
            Cloudinary::log("Asset save failed - Errors: " . json_encode($asset->getErrors()), 'error');
        }

        Cloudinary::log("=== AssetUploadAction Completed ===");
    }
}
