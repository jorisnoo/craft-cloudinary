<?php

namespace Noo\CraftCloudinary\services;

use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Asset;
use craft\helpers\Assets;
use Noo\CraftCloudinary\actions\FolderCreateAction;
use Noo\CraftCloudinary\Cloudinary;
use Noo\CraftCloudinary\fs\CloudinaryFs;
use yii\base\Component;

class SyncReconciler extends Component
{
    public function reconcile(int $volumeId): array
    {
        $volume = Craft::$app->getVolumes()->getVolumeById($volumeId);

        if ($volume === null) {
            throw new \InvalidArgumentException("Volume {$volumeId} not found");
        }

        $fs = $volume->getFs();

        if (!$fs instanceof CloudinaryFs) {
            throw new \InvalidArgumentException("Volume {$volumeId} does not use a Cloudinary filesystem");
        }

        $client = $fs->getClient();

        $cloudinaryAssets = $this->fetchAllCloudinaryAssets($client);
        $craftAssets = $this->fetchAllCraftAssets($volumeId);

        $stats = [
            'created' => 0,
            'deleted' => 0,
            'updated' => 0,
            'unchanged' => 0,
        ];

        $cloudinaryIndex = [];
        foreach ($cloudinaryAssets as $resource) {
            $key = $this->buildResourceKey($resource);
            $cloudinaryIndex[$key] = $resource;
        }

        $craftIndex = [];
        foreach ($craftAssets as $craftAsset) {
            $key = $craftAsset['folderPath'] . $craftAsset['filename'];
            $craftIndex[$key] = $craftAsset;
        }

        // Assets in Cloudinary but not in Craft -> create
        foreach ($cloudinaryIndex as $key => $resource) {
            if (isset($craftIndex[$key])) {
                // Check for metadata differences
                if ($this->hasMetadataChanged($craftIndex[$key], $resource)) {
                    $this->updateCraftAsset($craftIndex[$key], $resource);
                    $stats['updated']++;
                } else {
                    $stats['unchanged']++;
                }
                continue;
            }

            $this->createCraftAsset($volumeId, $resource);
            $stats['created']++;
        }

        // Assets in Craft but not in Cloudinary -> delete
        foreach ($craftIndex as $key => $craftAsset) {
            if (!isset($cloudinaryIndex[$key])) {
                $asset = Asset::find()->id($craftAsset['id'])->one();
                if ($asset) {
                    $asset->keepFileOnDelete = true;
                    Craft::$app->getElements()->deleteElement($asset);
                    $stats['deleted']++;
                }
            }
        }

        return $stats;
    }

    private function fetchAllCloudinaryAssets($client): array
    {
        $assets = [];
        $nextCursor = null;

        do {
            $search = $client->searchApi()
                ->expression('')
                ->maxResults(500)
                ->sortBy('created_at', 'desc');

            if ($nextCursor !== null) {
                $search->nextCursor($nextCursor);
            }

            $result = $search->execute();
            $resultArray = $result->getArrayCopy();

            foreach ($resultArray['resources'] ?? [] as $resource) {
                $assets[] = $resource;
            }

            $nextCursor = $resultArray['next_cursor'] ?? null;
        } while ($nextCursor !== null);

        return $assets;
    }

    private function fetchAllCraftAssets(int $volumeId): array
    {
        return (new Query())
            ->select([
                'assets.id',
                'assets.filename',
                'assets.size',
                'assets.width',
                'assets.height',
                'assets.folderId',
                'folders.path as folderPath',
            ])
            ->from(['assets' => Table::ASSETS])
            ->innerJoin(['elements' => Table::ELEMENTS], '[[elements.id]] = [[assets.id]]')
            ->innerJoin(['folders' => Table::VOLUMEFOLDERS], '[[folders.id]] = [[assets.folderId]]')
            ->where([
                'assets.volumeId' => $volumeId,
                'elements.dateDeleted' => null,
            ])
            ->all();
    }

    private function buildResourceKey(array $resource): string
    {
        $publicId = $resource['public_id'];
        $resourceType = $resource['resource_type'];
        $format = $resource['format'] ?? null;
        $assetFolder = $resource['asset_folder'] ?? '';

        $filename = basename($publicId);
        if ($resourceType !== 'raw' && $format) {
            $filename .= ".{$format}";
        }

        $folderPath = '';
        if ($assetFolder !== '') {
            $folderPath = trim($assetFolder, '/.') . '/';
        }

        return $folderPath . $filename;
    }

    private function hasMetadataChanged(array $craftAsset, array $cloudinaryResource): bool
    {
        $cloudinarySize = $cloudinaryResource['bytes'] ?? null;

        if ($cloudinarySize !== null && (int) $craftAsset['size'] !== $cloudinarySize) {
            return true;
        }

        if (($cloudinaryResource['resource_type'] ?? '') === 'image') {
            $cloudinaryWidth = $cloudinaryResource['width'] ?? null;
            $cloudinaryHeight = $cloudinaryResource['height'] ?? null;

            if ($cloudinaryWidth !== null && (int) $craftAsset['width'] !== $cloudinaryWidth) {
                return true;
            }

            if ($cloudinaryHeight !== null && (int) $craftAsset['height'] !== $cloudinaryHeight) {
                return true;
            }
        }

        return false;
    }

    private function updateCraftAsset(array $craftAsset, array $cloudinaryResource): void
    {
        $asset = Asset::find()->id($craftAsset['id'])->one();

        if ($asset === null) {
            return;
        }

        if (isset($cloudinaryResource['bytes'])) {
            $asset->size = $cloudinaryResource['bytes'];
        }

        if (($cloudinaryResource['resource_type'] ?? '') === 'image') {
            if (isset($cloudinaryResource['width'])) {
                $asset->width = $cloudinaryResource['width'];
            }

            if (isset($cloudinaryResource['height'])) {
                $asset->height = $cloudinaryResource['height'];
            }
        }

        $asset->setScenario(Asset::SCENARIO_INDEX);
        Craft::$app->getElements()->saveElement($asset);
    }

    private function createCraftAsset(int $volumeId, array $resource): void
    {
        $publicId = $resource['public_id'];
        $resourceType = $resource['resource_type'];
        $format = $resource['format'] ?? null;
        $assetFolder = $resource['asset_folder'] ?? '';
        $displayName = $resource['display_name'] ?? basename($publicId);

        $filename = basename($publicId);
        if ($resourceType !== 'raw' && $format) {
            $filename .= ".{$format}";
        }

        $folder = (new FolderCreateAction($volumeId))->firstOrCreate($assetFolder);

        $kind = Assets::getFileKindByExtension($filename);

        $asset = new Asset([
            'volumeId' => $volumeId,
            'folderId' => $folder->id,
            'filename' => $filename,
            'title' => $displayName,
            'kind' => $kind,
            'size' => $resource['bytes'] ?? 0,
            'dateCreated' => $resource['created_at'] ?? null,
            'dateModified' => $resource['created_at'] ?? null,
        ]);

        if ($kind === Asset::KIND_IMAGE) {
            $asset->width = $resource['width'] ?? null;
            $asset->height = $resource['height'] ?? null;
        }

        $asset->setScenario(Asset::SCENARIO_INDEX);

        $saved = Craft::$app->getElements()->saveElement($asset);

        if (!$saved) {
            Cloudinary::log("Reconciler: failed to create asset '{$publicId}': " . json_encode($asset->getErrors()), 'error');
        }
    }
}
