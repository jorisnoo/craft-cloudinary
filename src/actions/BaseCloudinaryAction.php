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
        $originalPath = $path;
        $path = Str::of($path)
            ->trim('/.')
            ->whenNotEmpty(fn($string) => $string->append('/'));

        // When the path is empty (it's the base folder), return null
        $result = $path->isEmpty() ? null : $path;
        Cloudinary::log("formatPath - Input: '{$originalPath}' => Output: '{$result}'");

        return $result;
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

        Cloudinary::log("formatFilename - Public ID: '{$publicId}', Type: '{$resourceType}', Format: '{$format}' => Filename: '{$filename}'");

        return $filename;
    }

    public function queryAsset(string $publicId, ?string $path, string $resourceType)
    {
        Cloudinary::log("queryAsset - Public ID: '{$publicId}', Path: '{$path}', Type: '{$resourceType}'");

        $filename = $this->formatFilename($publicId, $resourceType);

        $assetQuery = Asset::find()
            ->volumeId($this->volumeId)
            ->filename($filename)
            ->folderPath($path ?? '');

        if ($resourceType === AssetType::IMAGE) {
            $assetQuery->kind('image');
        } elseif($resourceType !== AssetType::RAW) {
            $assetQuery->kind(['video', 'audio']);
        }

        $result = $assetQuery->one();
        Cloudinary::log("queryAsset result: " . ($result ? "Found asset ID {$result->id}" : "No asset found"));

        return $result;
    }

    public function removePathFromPublicId(string $publicId, string $resourceType): void
    {
        if ($publicId !== basename($publicId)) {
            Cloudinary::log("Public ID contains path, dispatching cleanup job - Public ID: '{$publicId}', Type: '{$resourceType}'");

            \craft\helpers\Queue::push(new RemovePathFromCloudinaryPublicId(
                $this->volumeId,
                $publicId,
                $resourceType,
            ));
        } else {
            Cloudinary::log("Public ID is clean (no path): '{$publicId}'");
        }
    }
}
