<?php

namespace Noo\CraftCloudinary\actions;

use Cloudinary\Asset\AssetType;
use Craft;
use craft\elements\Asset;
use craft\elements\db\AssetQuery;
use craft\helpers\App;
use craft\services\Assets;
use craft\services\Volumes;
use Noo\CraftCloudinary\Cloudinary;
use Noo\CraftCloudinary\fs\CloudinaryFs;
use Noo\CraftCloudinary\helpers\AssetFolders;
use Noo\CraftCloudinary\helpers\PublicIds;
use Noo\CraftCloudinary\jobs\RemovePathFromCloudinaryPublicId;
use Noo\CraftCloudinary\services\SyncGuard;

abstract class BaseCloudinaryAction
{
    private ?string $volumeSubpath = null;

    private ?string $cloudName = null;

    public function __construct(public int $volumeId)
    {
    }

    public function formatPath($path): string
    {
        $path = trim((string) $path, '/.');

        return $path === '' ? '' : $path . '/';
    }

    /**
     * Converts an absolute Cloudinary asset folder into a path relative to the
     * volume subpath. Returns null when the folder lies outside the subpath,
     * meaning the event does not concern this volume.
     */
    public function relativeAssetFolder(?string $assetFolder): ?string
    {
        return AssetFolders::relativeToSubpath((string) $assetFolder, $this->volumeSubpath());
    }

    protected function volumeSubpath(): string
    {
        return $this->volumeSubpath ??= $this->volumesService()
            ->getVolumeById($this->volumeId)
            ?->getSubpath(false) ?? '';
    }

    protected function cloudName(): string
    {
        if ($this->cloudName === null) {
            $fs = $this->volumesService()->getVolumeById($this->volumeId)?->getFs();
            $this->cloudName = $fs instanceof CloudinaryFs ? App::parseEnv($fs->cloudName) : '';
        }

        return $this->cloudName;
    }

    /**
     * Whether Craft itself made this change in Cloudinary recently, so the
     * webhook only echoes it. See SyncGuard::markChangedFromCraft().
     */
    protected function wasChangedFromCraft(string $subject): bool
    {
        $syncGuard = Cloudinary::getInstance()?->syncGuard;

        return $syncGuard !== null && $syncGuard->wasChangedFromCraft($this->cloudName(), $subject);
    }

    /**
     * Folder changes made from Craft also cover their subfolders.
     *
     * @param ?string $folderPath absolute Cloudinary folder path
     */
    protected function folderWasChangedFromCraft(?string $folderPath): bool
    {
        $folderPath = trim((string) $folderPath, '/');

        while ($folderPath !== '' && $folderPath !== '.') {
            if ($this->wasChangedFromCraft(SyncGuard::folderKey($folderPath))) {
                return true;
            }

            $folderPath = dirname($folderPath);
        }

        return false;
    }

    protected function logSkippedCraftChange(string $subject): void
    {
        Cloudinary::log("Skipping webhook for {$subject} - the change was made from Craft");
    }

    protected function logSkippedOutsideSubpath(string $subject, string $assetFolder): void
    {
        Cloudinary::log(
            "Skipping webhook for {$subject} - asset folder '{$assetFolder}' is outside the volume subpath '{$this->volumeSubpath()}'",
        );
    }

    public function formatFilename(string $publicId, string $resourceType, ?string $format = null): string
    {
        $filename = basename($publicId);

        if ($resourceType !== AssetType::RAW && $format !== null && $format !== '') {
            $filename .= ".{$format}";
        }

        return $filename;
    }

    public function queryAsset(string $publicId, ?string $path, string $resourceType): ?Asset
    {
        $assets = $this->queryAssets($publicId, $path, $resourceType);

        if (count($assets) > 1) {
            $this->logAmbiguousAsset($publicId, $this->formatPath($path), $resourceType);
            return null;
        }

        return $assets[0] ?? null;
    }

    /**
     * All Craft assets in the folder stored as the given Cloudinary asset.
     * Usually one, but earlier versions could create duplicates.
     *
     * @return Asset[]
     */
    public function queryAssets(string $publicId, ?string $path, string $resourceType): array
    {
        $path = $this->formatPath($path);

        // findFolder() ignores an empty path criteria, so the root folder
        // has to be resolved explicitly.
        $folder = $path === ''
            ? $this->assetsService()->getRootFolderByVolumeId($this->volumeId)
            : $this->assetsService()->findFolder([
                'volumeId' => $this->volumeId,
                'path' => $path,
            ]);

        if ($folder === null) {
            return [];
        }

        $filename = $this->formatFilename($publicId, $resourceType);

        if ($resourceType !== AssetType::RAW) {
            $filename .= '.*';
        }

        $candidates = $this->createAssetQuery()
            ->volumeId($this->volumeId)
            ->filename($filename)
            ->folderId($folder->id)
            ->all();

        // The filename pattern also matches files stored under another
        // resource type (photo.zip for image "photo"), so keep only the
        // assets stored as this exact Cloudinary asset.
        $key = PublicIds::keyFromResource($resourceType, $publicId);
        return array_values(array_filter(
            $candidates,
            static fn(Asset $asset) => PublicIds::keyFromPath((string) $asset->getFilename()) === $key,
        ));
    }

    protected function assetsService(): Assets
    {
        return Craft::$app->getAssets();
    }

    protected function volumesService(): Volumes
    {
        return Craft::$app->getVolumes();
    }

    protected function createAssetQuery(): AssetQuery
    {
        return Asset::find();
    }

    protected function logAmbiguousAsset(string $publicId, string $path, string $resourceType): void
    {
        Cloudinary::log(
            "Ambiguous asset lookup - Public ID: {$publicId}, Folder: {$path}, Type: {$resourceType}",
            'warning',
        );
    }

    public function removePathFromPublicId(string $publicId, string $resourceType): void
    {
        if ($publicId !== basename($publicId)) {
            \craft\helpers\Queue::push(new RemovePathFromCloudinaryPublicId(
                $this->volumeId,
                $publicId,
                $resourceType,
            ));
        }
    }
}
