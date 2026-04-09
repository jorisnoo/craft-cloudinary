<?php

namespace Noo\CraftCloudinary\actions;

use Cloudinary\Asset\AssetType;
use craft\elements\Asset;
use Noo\CraftCloudinary\jobs\RemovePathFromCloudinaryPublicId;

abstract class BaseCloudinaryAction
{
    public function __construct(public int $volumeId)
    {
    }

    public function formatPath($path): ?string
    {
        $path = trim((string) $path, '/.');

        return $path === '' ? null : $path . '/';
    }

    public function formatFilename(string $publicId, string $resourceType, string $format = '*'): string
    {
        $filename = basename($publicId);

        if ($resourceType !== AssetType::RAW) {
            $filename .= ".{$format}";
        }

        return $filename;
    }

    public function queryAsset(string $publicId, ?string $path, string $resourceType): ?Asset
    {
        $filename = $this->formatFilename($publicId, $resourceType);

        $assetQuery = Asset::find()
            ->volumeId($this->volumeId)
            ->filename($filename)
            ->folderPath($path ?? '');

        if ($resourceType === AssetType::IMAGE) {
            $assetQuery->kind('image');
        } elseif ($resourceType !== AssetType::RAW) {
            $assetQuery->kind(['video', 'audio']);
        }

        return $assetQuery->one();
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
