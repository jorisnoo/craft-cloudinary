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
    ): void {
        $this->removePathFromPublicId($publicId, $resourceType);

        $folder = (new FolderCreateAction($this->volumeId))->firstOrCreate($assetFolder);
        $filename = $this->formatFilename($publicId, $resourceType, $format);

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
}
