<?php

namespace Noo\CraftCloudinary\fs;

use Cloudinary\Api\ApiResponse;
use Cloudinary\Api\Exception\NotFound;
use Cloudinary\Asset\AssetType;
use Cloudinary\Cloudinary;
use Generator;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCheckDirectoryExistence;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToGeneratePublicUrl;
use League\Flysystem\UnableToListContents;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use League\Flysystem\Visibility;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use League\MimeTypeDetection\MimeTypeDetector;
use Noo\CraftCloudinary\helpers\PublicIds;
use Noo\CraftCloudinary\helpers\ResourceTypes;
use Throwable;

/**
 * Flysystem adapter for Cloudinary in dynamic folders mode.
 *
 * Based on thomasvantuycom/flysystem-cloudinary v2.0.2 (MIT License,
 * Copyright (c) 2024 Thomas Vantuycom). Changes from upstream:
 * - Fixed folders mode is not supported.
 * - Delivery URLs are built locally instead of through an Admin API call,
 *   so reads no longer cost a rate-limited request per file.
 * - Public IDs and resource types come from the plugin's helpers, so the
 *   adapter and the rest of the plugin cannot disagree on them.
 * - Copies upload from the source's delivery URL instead of streaming the
 *   file through PHP.
 * - Moves and copies never overwrite an existing asset. Public IDs are
 *   unique across the whole account, so an overwrite could replace an
 *   unrelated asset in another folder.
 */
class CloudinaryAdapter implements FilesystemAdapter, PublicUrlGenerator
{
    private MimeTypeDetector $mimeTypeDetector;

    public function __construct(
        private Cloudinary $client,
        ?MimeTypeDetector $mimeTypeDetector = null,
    ) {
        $this->mimeTypeDetector = $mimeTypeDetector ?? new FinfoMimeTypeDetector();
    }

    public function fileExists(string $path): bool
    {
        try {
            $this->resource($path);

            return true;
        } catch (NotFound) {
            return false;
        } catch (Throwable $e) {
            throw UnableToCheckFileExistence::forLocation($path, $e);
        }
    }

    public function directoryExists(string $path): bool
    {
        try {
            $this->client->adminApi()->subFolders($path, [
                'max_results' => 1,
            ]);

            return true;
        } catch (NotFound) {
            return false;
        } catch (Throwable $e) {
            throw UnableToCheckDirectoryExistence::forLocation($path, $e);
        }
    }

    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $stream = fopen('php://temp', 'w+b');
            fwrite($stream, $contents);
            rewind($stream);

            $this->writeStream($path, $stream, $config);
        } catch (Throwable $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        try {
            // Overwriting is intended here: Craft replaces an asset's file
            // by writing to the same path.
            $this->upload($path, $contents, overwrite: true);
        } catch (Throwable $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function read(string $path): string
    {
        try {
            $stream = $this->readStream($path);

            return stream_get_contents($stream);
        } catch (Throwable $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    public function readStream(string $path)
    {
        try {
            $stream = fopen($this->publicUrl($path, new Config()), 'rb');

            if ($stream === false) {
                throw new \RuntimeException('Unable to open the delivery URL');
            }

            return $stream;
        } catch (Throwable $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    public function delete(string $path): void
    {
        try {
            $this->client->uploadApi()->destroy(PublicIds::fromPath($path), [
                'resource_type' => ResourceTypes::fromPath($path),
                'type' => 'upload',
                'invalidate' => true,
            ]);
        } catch (Throwable $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        try {
            foreach ($this->listContents($path, true) as $item) {
                if ($item->isFile()) {
                    $this->delete($item->path());
                }
            }

            $this->client->adminApi()->deleteFolder($path);
        } catch (UnableToListContents) {
            // The folder doesn't exist, so there is nothing to delete.
        } catch (Throwable $e) {
            throw UnableToDeleteDirectory::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        try {
            $this->client->adminApi()->createFolder($path);
        } catch (Throwable $e) {
            throw UnableToCreateDirectory::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function setVisibility(string $path, string $visibility): void
    {
        throw UnableToSetVisibility::atLocation($path, 'Cloudinary does not support this operation.');
    }

    public function visibility(string $path): FileAttributes
    {
        try {
            $this->resource($path);

            return new FileAttributes($path, visibility: Visibility::PUBLIC);
        } catch (Throwable $e) {
            throw UnableToRetrieveMetadata::visibility($path, $e->getMessage(), $e);
        }
    }

    public function mimeType(string $path): FileAttributes
    {
        try {
            $this->resource($path);
            $mimeType = $this->mimeTypeDetector->detectMimeTypeFromPath($path);

            if ($mimeType === null) {
                throw UnableToRetrieveMetadata::mimeType($path);
            }

            return new FileAttributes($path, mimeType: $mimeType);
        } catch (Throwable $e) {
            throw UnableToRetrieveMetadata::mimeType($path, $e->getMessage(), $e);
        }
    }

    public function lastModified(string $path): FileAttributes
    {
        try {
            $resource = $this->resource($path);

            return new FileAttributes($path, lastModified: strtotime($resource['created_at']));
        } catch (Throwable $e) {
            throw UnableToRetrieveMetadata::lastModified($path, $e->getMessage(), $e);
        }
    }

    public function fileSize(string $path): FileAttributes
    {
        try {
            $resource = $this->resource($path);

            return new FileAttributes($path, fileSize: $resource['bytes']);
        } catch (Throwable $e) {
            throw UnableToRetrieveMetadata::fileSize($path, $e->getMessage(), $e);
        }
    }

    public function listContents(string $path, bool $deep): iterable
    {
        try {
            yield from $this->listFiles($path);

            foreach ($this->listFolders($path, $deep) as $directoryAttributes) {
                yield $directoryAttributes;

                if ($deep) {
                    yield from $this->listFiles($directoryAttributes->path());
                }
            }
        } catch (Throwable $e) {
            throw UnableToListContents::atLocation($path, $deep, $e);
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $sourcePublicId = PublicIds::fromPath($source);
            $destinationPublicId = PublicIds::fromPath($destination);

            $options = [
                'resource_type' => ResourceTypes::fromPath($destination),
                'asset_folder' => $this->folder($destination),
                'invalidate' => true,
            ];

            if ($destinationPublicId === $sourcePublicId) {
                $this->client->adminApi()->update($sourcePublicId, $options);
            } else {
                $this->client->uploadApi()->rename($sourcePublicId, $destinationPublicId, $options + [
                    'overwrite' => false,
                ]);
            }
        } catch (Throwable $e) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $e);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            // Cloudinary fetches the source itself, so the file never passes
            // through PHP.
            $this->upload($destination, $this->publicUrl($source, new Config()), overwrite: false);
        } catch (Throwable $e) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $e);
        }
    }

    public function publicUrl(string $path, Config $config): string
    {
        try {
            // For image/video URLs the extension acts as the delivery format;
            // for raw files it is part of the public ID. Either way the URL
            // path component is the plain filename.
            $filename = basename($path);

            $resource = match (ResourceTypes::fromPath($path)) {
                AssetType::IMAGE => $this->client->image($filename),
                AssetType::VIDEO => $this->client->video($filename),
                default => $this->client->raw($filename),
            };

            return (string) $resource->toUrl();
        } catch (Throwable $e) {
            throw UnableToGeneratePublicUrl::dueToError($path, $e);
        }
    }

    /**
     * @param resource|string $file a stream, or a URL for Cloudinary to fetch
     */
    private function upload(string $path, $file, bool $overwrite): void
    {
        $options = [
            'public_id' => PublicIds::fromPath($path),
            'resource_type' => ResourceTypes::fromPath($path),
            'filename' => $path,
            'overwrite' => $overwrite,
            'invalidate' => true,
        ];

        if (($folder = $this->folder($path)) !== '') {
            $options['asset_folder'] = $folder;
        }

        $response = $this->client->uploadApi()->upload($file, $options);

        // With overwrite disabled, Cloudinary reports success for an
        // existing public ID but leaves the asset untouched.
        if (!$overwrite && !empty($response['existing'])) {
            throw new \RuntimeException("An asset with the public ID '{$options['public_id']}' already exists");
        }
    }

    private function resource(string $path): ApiResponse
    {
        return $this->client->uploadApi()->explicit(PublicIds::fromPath($path), [
            'resource_type' => ResourceTypes::fromPath($path),
            'type' => 'upload',
        ]);
    }

    private function listFiles(string $path): Generator
    {
        $nextCursor = null;

        do {
            $response = $this->client->adminApi()->assetsByAssetFolder($path, [
                'max_results' => 500,
                'next_cursor' => $nextCursor,
            ]);

            foreach ($response['resources'] as $resource) {
                yield $this->mapResourceAttributes($resource);
            }

            $nextCursor = $response['next_cursor'] ?? null;
        } while ($nextCursor !== null);
    }

    private function listFolders(string $path, bool $deep): Generator
    {
        $nextCursor = null;

        do {
            $options = [
                'max_results' => 500,
                'next_cursor' => $nextCursor,
            ];

            $response = $path === ''
                ? $this->client->adminApi()->rootFolders($options)
                : $this->client->adminApi()->subFolders($path, $options);

            foreach ($response['folders'] as $folder) {
                $directoryAttributes = new DirectoryAttributes($folder['path']);

                yield $directoryAttributes;

                if ($deep) {
                    yield from $this->listFolders($directoryAttributes->path(), $deep);
                }
            }

            $nextCursor = $response['next_cursor'] ?? null;
        } while ($nextCursor !== null);
    }

    private function mapResourceAttributes(array $resource): FileAttributes
    {
        $path = $resource['public_id'];

        if ($resource['resource_type'] !== AssetType::RAW) {
            $path .= '.' . $resource['format'];
        }

        if ($resource['asset_folder'] !== '') {
            $path = $resource['asset_folder'] . '/' . $path;
        }

        return new FileAttributes(
            $path,
            $resource['bytes'],
            Visibility::PUBLIC,
            strtotime($resource['created_at']),
            $this->mimeTypeDetector->detectMimeTypeFromPath($path),
        );
    }

    private function folder(string $path): string
    {
        $dirname = pathinfo($path, PATHINFO_DIRNAME);

        return $dirname === '.' ? '' : $dirname;
    }
}
