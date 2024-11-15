<?php

namespace jorisnoo\craftcloudinary\actions;

use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Asset;
use craft\helpers\Assets;

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
    ): void
    {
        $this->removePathFromPublicId($publicId, $resourceType);

        // First, get or create the asset folder
        $folder = (new FolderCreateAction($this->volumeId))->firstOrCreate($assetFolder);

        // Prepare the filename
        $filename = $this->formatFilename($publicId, $resourceType, $format);

        // Check if the asset already exists
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
            return;
        }

        // Otherwise, store it
        $kind = Assets::getFileKindByExtension($filename);

        $asset = new Asset([
            'volumeId' => $this->volumeId,
            'folderId' => $folder->id,
            'filename' => $filename,
            'title' => $displayName,
            'kind' => $kind,
            'size' => $size,
        ]);

        if ($kind === Asset::KIND_IMAGE) {
            $asset->width = $width;
            $asset->height = $height;
        }

        $asset->setScenario(Asset::SCENARIO_INDEX);

        Craft::$app->getElements()->saveElement($asset);
    }
}
