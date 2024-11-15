<?php

namespace jorisnoo\craftcloudinary\actions;

use Cloudinary\Asset\AssetType;
use craft\elements\Asset;
use Illuminate\Support\Str;
use jorisnoo\craftcloudinary\Cloudinary;
use jorisnoo\craftcloudinary\jobs\RemovePathFromCloudinaryPublicId;

abstract class BaseCloudinaryAction
{
    public function __construct(public int $volumeId)
    {}

    public function formatPath($path): ?string
    {
        $path = Str::of($path)
            ->trim('/.')
            ->whenNotEmpty(fn($string) => $string->append('/'));

        // When the path is empty (it's the base folder), return null
        return $path->isEmpty() ? null : $path;
    }

    public function formatFilename(string $publicId, string $resourceType, string $format = '*'): string
    {
        // Check if the asset exists
        // The public id should never contain a path, but just in case strip it
        $filename = basename($publicId);

        // Unless it is a "raw" asset, add the extionsion to the filename
        if ($resourceType !== AssetType::RAW) {
            $filename .= ".{$format}";
        }

        return $filename;
    }

    public function queryAsset(string $publicId, string $path, string $resourceType)
    {
        $filename = $this->formatFilename($publicId, $resourceType);

        $assetQuery = Asset::find()
            ->volumeId($this->volumeId)
            ->filename($filename)
            ->folderPath($path);

        if ($resourceType === AssetType::IMAGE) {
            $assetQuery->kind('image');
        } elseif($resourceType !== AssetType::RAW) {
            $assetQuery->kind(['video', 'audio']);
        }

        return $assetQuery->one();
    }

    public function removePathFromPublicId(string $publicId, string $resourceType): void
    {
        if ($publicId !== basename($publicId)) {
            Cloudinary::log("Dispatching job to remove path from public_id '$publicId'");

            \craft\helpers\Queue::push(new RemovePathFromCloudinaryPublicId(
                $this->volumeId,
                $publicId,
                $resourceType,
            ));
        }
    }
}
