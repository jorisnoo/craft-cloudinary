<?php

namespace Noo\CraftCloudinary\actions;

use Craft;
use craft\elements\Asset;
use craft\helpers\Assets;
use Noo\CraftCloudinary\Cloudinary;
use Noo\CraftCloudinary\helpers\PublicIds;

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
    ): void {
        $filename = $this->formatFilename($publicId, $resourceType, $format);
        $assetKey = PublicIds::keyFromResource($resourceType, $publicId, $format);

        if (Cloudinary::getInstance()->syncGuard->wasUploadedFromCraft($this->cloudName(), $assetKey)) {
            Cloudinary::log("Skipping webhook upload for '{$filename}' - already uploaded from Craft");
            return;
        }

        $relativeFolder = $this->relativeAssetFolder($assetFolder);

        if ($relativeFolder === null) {
            $this->logSkippedOutsideSubpath("upload of '{$publicId}'", $assetFolder);
            return;
        }

        $this->removePathFromPublicId($publicId, $resourceType);

        $folder = (new FolderCreateAction($this->volumeId))->firstOrCreate($relativeFolder);

        $existingAssets = $this->queryAssets($publicId, $relativeFolder, $resourceType);

        if ($existingAssets !== []) {
            // The file was overwritten in Cloudinary.
            foreach ($existingAssets as $existingAsset) {
                $this->updateMetadata($existingAsset, $size, $width, $height);
            }
            return;
        }

        $kind = Assets::getFileKindByExtension($filename);

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
        }

        $asset->setScenario(Asset::SCENARIO_INDEX);

        $saved = Craft::$app->getElements()->saveElement($asset);

        if (!$saved) {
            Cloudinary::log("Asset upload failed - Public ID: {$publicId}, Errors: " . json_encode($asset->getErrors()), 'error');
        }
    }

    protected function updateMetadata(Asset $asset, int $size, ?int $width, ?int $height): void
    {
        $isImage = $asset->kind === Asset::KIND_IMAGE;

        if (
            (int) $asset->size === $size &&
            (!$isImage || ((int) $asset->getWidth() === (int) $width && (int) $asset->getHeight() === (int) $height))
        ) {
            return;
        }

        $asset->size = $size;

        if ($isImage) {
            $asset->setWidth($width);
            $asset->setHeight($height);
        }

        $asset->setScenario(Asset::SCENARIO_INDEX);
        Craft::$app->getElements()->saveElement($asset);

        Cloudinary::log("Updated metadata of overwritten asset {$asset->id} - Filename: {$asset->filename}");
    }
}
