<?php

namespace Noo\CraftCloudinary\helpers;

use craft\models\FsListing;

class FsListings
{
    /**
     * Maps Cloudinary Search API resources to Craft filesystem listings.
     *
     * Paths are absolute filesystem paths including the volume subpath -
     * Craft strips the subpath itself. Directory listings are derived from
     * the asset folders of the resources, so folders without any assets
     * never appear, matching the reconciler's behavior.
     *
     * A filename can't be rebuilt reliably from a resource: Cloudinary
     * reports the detected format, not the uploaded extension. When Craft
     * already has an asset stored as the resource, its filename is listed,
     * so the indexer doesn't treat `photo.jpeg` as missing and `photo.jpg`
     * as new.
     *
     * @param iterable<array> $resources
     * @param string $directory absolute path of the listed directory
     * @param array<string, string> $knownFilenames Craft filenames by knownFilenameKey()
     * @return \Generator<int, FsListing>
     */
    public static function fromResources(iterable $resources, string $directory, bool $recursive, array $knownFilenames = []): \Generator
    {
        $directory = trim($directory, '/');
        $seenFolders = [];

        foreach ($resources as $resource) {
            $folder = trim($resource['asset_folder'] ?? '', '/');
            $relativeFolder = AssetFolders::relativeToSubpath($folder, $directory);

            if ($relativeFolder === null) {
                continue;
            }

            $segments = $relativeFolder === '' ? [] : explode('/', $relativeFolder);

            if (!$recursive) {
                // Only direct child folders are listed, without descending.
                $segments = array_slice($segments, 0, 1);
            }

            $path = $directory;

            foreach ($segments as $segment) {
                $path = $path === '' ? $segment : "{$path}/{$segment}";

                if (!isset($seenFolders[$path])) {
                    $seenFolders[$path] = true;
                    yield self::listing($path, 'dir');
                }
            }

            if ($recursive || $relativeFolder === '') {
                yield self::fileListing($resource, $folder, $knownFilenames);
            }
        }
    }

    /**
     * @param string $folder absolute Cloudinary folder path
     * @param string $assetKey see PublicIds::key()
     */
    public static function knownFilenameKey(string $folder, string $assetKey): string
    {
        return trim($folder, '/') . '|' . $assetKey;
    }

    private static function fileListing(array $resource, string $folder, array $knownFilenames): FsListing
    {
        $knownFilename = $knownFilenames[self::knownFilenameKey(
            $folder,
            PublicIds::keyFromResource($resource['resource_type'] ?? '', $resource['public_id'], $resource['format'] ?? null),
        )] ?? null;

        if ($knownFilename !== null) {
            $filename = $knownFilename;
        } else {
            $filename = basename($resource['public_id']);
            $format = $resource['format'] ?? null;

            if (($resource['resource_type'] ?? '') !== 'raw' && $format) {
                $filename .= ".{$format}";
            }
        }

        $path = $folder === '' ? $filename : "{$folder}/{$filename}";
        $createdAt = $resource['created_at'] ?? null;

        return self::listing(
            $path,
            'file',
            $createdAt ? strtotime($createdAt) : null,
            $resource['bytes'] ?? null,
        );
    }

    private static function listing(string $path, string $type, ?int $dateModified = null, ?int $fileSize = null): FsListing
    {
        return new FsListing([
            'dirname' => pathinfo($path, PATHINFO_DIRNAME),
            'basename' => pathinfo($path, PATHINFO_BASENAME),
            'type' => $type,
            'dateModified' => $dateModified,
            'fileSize' => $fileSize,
        ]);
    }
}
