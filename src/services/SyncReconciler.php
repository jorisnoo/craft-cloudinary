<?php

namespace Noo\CraftCloudinary\services;

use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Asset;
use craft\helpers\Assets;
use Noo\CraftCloudinary\actions\FolderCreateAction;
use Noo\CraftCloudinary\Cloudinary;
use Noo\CraftCloudinary\exceptions\ReconciliationAbortedException;
use Noo\CraftCloudinary\fs\CloudinaryFs;
use Noo\CraftCloudinary\helpers\AssetFolders;
use Noo\CraftCloudinary\helpers\CloudinaryAssetSearch;
use Noo\CraftCloudinary\helpers\PublicIds;
use yii\base\Component;

class SyncReconciler extends Component
{
    private const MAX_DELETE_RATIO = 0.10;

    // The Search API index can lag behind uploads, so recently created Craft
    // assets may be missing from the results. Don't delete them. The window
    // is generous on purpose: deleting an asset loses its relations.
    private const DELETE_GRACE_SECONDS = 3600;

    public function reconcile(int $volumeId, bool $dryRun = false, bool $force = false): array
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

        $cloudinaryAssets = $this->fetchAllCloudinaryAssets($client, $volume->getSubpath(false));
        $craftAssets = $this->fetchAllCraftAssets($volumeId);

        $stats = [
            'created' => 0,
            'deleted' => 0,
            'updated' => 0,
            'unchanged' => 0,
        ];

        if (count($cloudinaryAssets) === 0 && count($craftAssets) > 0) {
            $message = "Reconciler: aborted for volume {$volumeId}: Cloudinary returned 0 assets but Craft has " . count($craftAssets) . ". Refusing to delete.";
            Cloudinary::log($message, 'error');
            throw new ReconciliationAbortedException(
                $message,
                $volumeId,
                ReconciliationAbortedException::REASON_EMPTY_RESPONSE,
                count($craftAssets),
                count($craftAssets),
            );
        }

        $cloudinaryIndex = [];
        foreach ($cloudinaryAssets as $resource) {
            $key = $this->buildResourceKey($resource);
            $cloudinaryIndex[$key] = $resource;
        }

        // Several Craft assets can point at the same Cloudinary asset, e.g.
        // duplicates created by earlier versions for `.jpeg` uploads. All of
        // them count as present - choosing one to delete could drop the one
        // with relations.
        $craftIndex = [];
        foreach ($craftAssets as $craftAsset) {
            $key = $this->buildCraftAssetKey($craftAsset);
            $craftIndex[$key][] = $craftAsset;
        }

        $toDelete = [];
        $inGracePeriod = 0;
        foreach ($craftIndex as $key => $craftAssetsForKey) {
            if (isset($cloudinaryIndex[$key])) {
                continue;
            }

            foreach ($craftAssetsForKey as $craftAsset) {
                if ($this->isWithinDeleteGracePeriod($craftAsset['dateCreated'] ?? null)) {
                    $inGracePeriod++;
                    continue;
                }

                $toDelete[] = $craftAsset;
            }
        }

        if ($inGracePeriod > 0) {
            Cloudinary::log(sprintf(
                'Reconciler: skipped deleting %d asset(s) created within the last %d seconds - the Search API may not have indexed them yet',
                $inGracePeriod,
                self::DELETE_GRACE_SECONDS,
            ));
        }

        $craftCount = count($craftAssets);
        $deleteRatio = $craftCount > 0 ? count($toDelete) / $craftCount : 0.0;

        if (!$force && $deleteRatio > self::MAX_DELETE_RATIO) {
            $ids = array_column($toDelete, 'id');
            $message = sprintf(
                "Reconciler: aborted for volume %d: would delete %d/%d (%.1f%%) Craft assets, exceeding %.0f%% threshold. Asset IDs: %s",
                $volumeId,
                count($toDelete),
                $craftCount,
                $deleteRatio * 100,
                self::MAX_DELETE_RATIO * 100,
                implode(',', $ids)
            );
            Cloudinary::log($message, 'error');
            throw new ReconciliationAbortedException(
                $message,
                $volumeId,
                ReconciliationAbortedException::REASON_DELETION_RATIO,
                count($toDelete),
                $craftCount,
                $ids,
            );
        }

        // Assets in Cloudinary but not in Craft -> create
        foreach ($cloudinaryIndex as $key => $resource) {
            if (isset($craftIndex[$key])) {
                if (count($craftIndex[$key]) > 1) {
                    Cloudinary::log(sprintf(
                        "Reconciler: Craft assets %s all point at Cloudinary asset '%s' - keeping all of them, remove the duplicates manually",
                        implode(',', array_column($craftIndex[$key], 'id')),
                        $resource['public_id'],
                    ), 'warning');
                }

                foreach ($craftIndex[$key] as $craftAsset) {
                    if ($this->hasMetadataChanged($craftAsset, $resource)) {
                        if (!$dryRun) {
                            $this->updateCraftAsset($craftAsset, $resource);
                        }
                        $stats['updated']++;
                    } else {
                        $stats['unchanged']++;
                    }
                }
                continue;
            }

            if (!$dryRun) {
                $this->createCraftAsset($volumeId, $resource);
            }
            $stats['created']++;
        }

        // Assets in Craft but not in Cloudinary -> delete
        foreach ($toDelete as $craftAsset) {
            if (!$dryRun) {
                $asset = Asset::find()->id($craftAsset['id'])->one();
                if ($asset) {
                    $asset->keepFileOnDelete = true;
                    Craft::$app->getElements()->deleteElement($asset);
                }
            }
            $stats['deleted']++;
        }

        return $stats;
    }

    private function fetchAllCloudinaryAssets($client, string $volumeSubpath = ''): array
    {
        $assets = [];
        $volumeSubpath = trim($volumeSubpath, '/');

        try {
            $resources = CloudinaryAssetSearch::resources($client, $volumeSubpath, [
                'public_id',
                'asset_folder',
                'display_name',
                'resource_type',
                'format',
                'bytes',
                'width',
                'height',
                'created_at',
            ]);

            foreach ($resources as $resource) {
                $relativeFolder = AssetFolders::relativeToSubpath(
                    $resource['asset_folder'] ?? '',
                    $volumeSubpath,
                );

                if ($relativeFolder === null) {
                    continue;
                }

                $resource['asset_folder'] = $relativeFolder;
                $assets[] = $resource;
            }
        } catch (\Throwable $e) {
            Cloudinary::log(
                "Reconciler: Search API listing failed for volume subpath={$volumeSubpath}: {$e->getMessage()}",
                'error',
            );
            throw $e;
        }

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
                'elements.dateCreated',
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

    private function isWithinDeleteGracePeriod(?string $dateCreated): bool
    {
        if ($dateCreated === null || $dateCreated === '') {
            return false;
        }

        $created = new \DateTimeImmutable($dateCreated, new \DateTimeZone('UTC'));
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $now->getTimestamp() - $created->getTimestamp() < self::DELETE_GRACE_SECONDS;
    }

    /**
     * Keys on the public ID rather than a filename rebuilt from the format,
     * which wouldn't match Craft's filename when Cloudinary reports a format
     * other than the uploaded extension (`photo.jpeg` has format `jpg`).
     */
    private function buildResourceKey(array $resource): string
    {
        return $this->folderPrefix($resource['asset_folder'] ?? '')
            . PublicIds::keyFromResource($resource['resource_type'], $resource['public_id'], $resource['format'] ?? null);
    }

    private function buildCraftAssetKey(array $craftAsset): string
    {
        return $this->folderPrefix($craftAsset['folderPath'] ?? '')
            . PublicIds::keyFromPath($craftAsset['filename']);
    }

    private function folderPrefix(?string $folderPath): string
    {
        $folderPath = trim((string) $folderPath, '/.');

        return $folderPath === '' ? '' : $folderPath . '/';
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
