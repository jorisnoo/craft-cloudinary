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
use Noo\CraftCloudinary\jobs\RemovePathFromCloudinaryPublicId;

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
            return null;
        }

        $filename = $this->formatFilename($publicId, $resourceType);

        if ($resourceType !== AssetType::RAW) {
            $filename .= '.*';
        }

        $assetQuery = $this->createAssetQuery()
            ->volumeId($this->volumeId)
            ->filename($filename)
            ->folderId($folder->id);

        if ($resourceType === AssetType::IMAGE) {
            $assetQuery->kind('image');
        } elseif ($resourceType !== AssetType::RAW) {
            $assetQuery->kind(['video', 'audio']);
        }

        $assets = $assetQuery->limit(2)->all();

        if (count($assets) > 1) {
            $this->logAmbiguousAsset($publicId, $path, $resourceType);
            return null;
        }

        return $assets[0] ?? null;
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
