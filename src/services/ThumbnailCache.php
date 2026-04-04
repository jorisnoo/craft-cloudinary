<?php

namespace Noo\CraftCloudinary\services;

use Craft;
use craft\helpers\FileHelper;
use Noo\CraftCloudinary\Cloudinary;
use yii\base\Component;

class ThumbnailCache extends Component
{
    private const PENDING_MAX_AGE = 300; // 5 minutes

    public function getCacheDir(): string
    {
        return Craft::getAlias('@storage/runtime/cloudinary-thumbs');
    }

    private function getAssetDir(int $assetId): string
    {
        return $this->getCacheDir() . DIRECTORY_SEPARATOR . $assetId;
    }

    public function has(int $assetId, int $width, int $height): bool
    {
        $path = $this->findCachedFile($assetId, $width, $height);

        return $path !== null && !$this->isExpired($path);
    }

    public function tryMarkPending(int $assetId, int $width, int $height): bool
    {
        $path = $this->getPendingPath($assetId, $width, $height);

        // Clean up stale pending files
        if (file_exists($path) && (time() - filemtime($path)) > self::PENDING_MAX_AGE) {
            @unlink($path);
        }

        FileHelper::createDirectory($this->getAssetDir($assetId));

        // Atomic: fopen with 'x' fails if the file already exists
        $handle = @fopen($path, 'x');
        if ($handle === false) {
            return false;
        }

        fclose($handle);
        return true;
    }

    public function clearPending(int $assetId, int $width, int $height): void
    {
        $path = $this->getPendingPath($assetId, $width, $height);

        if (file_exists($path)) {
            @unlink($path);
        }
    }

    private function getPendingPath(int $assetId, int $width, int $height): string
    {
        return $this->getAssetDir($assetId) . DIRECTORY_SEPARATOR . "{$width}x{$height}.pending";
    }

    public function get(int $assetId, int $width, int $height): ?string
    {
        $path = $this->findCachedFile($assetId, $width, $height);

        if ($path === null || $this->isExpired($path)) {
            return null;
        }

        return $path;
    }

    public function put(int $assetId, int $width, int $height, string $contents, string $extension): string
    {
        $dir = $this->getAssetDir($assetId);
        FileHelper::createDirectory($dir);

        $path = $dir . DIRECTORY_SEPARATOR . "{$width}x{$height}.{$extension}";
        file_put_contents($path, $contents);

        return $path;
    }

    public function invalidateAsset(int $assetId): void
    {
        $dir = $this->getAssetDir($assetId);

        if (is_dir($dir)) {
            FileHelper::removeDirectory($dir);
        }
    }

    public function invalidateAll(): void
    {
        $dir = $this->getCacheDir();

        if (is_dir($dir)) {
            FileHelper::removeDirectory($dir);
        }
    }

    public function cleanup(): int
    {
        $dir = $this->getCacheDir();

        if (!is_dir($dir)) {
            return 0;
        }

        $removed = 0;

        foreach (FileHelper::findFiles($dir) as $file) {
            if (str_ends_with($file, '.pending')) {
                if ((time() - filemtime($file)) > self::PENDING_MAX_AGE) {
                    @unlink($file);
                }
                continue;
            }

            if ($this->isExpired($file)) {
                unlink($file);
                $removed++;
            }
        }

        // Remove empty asset directories
        foreach (FileHelper::findDirectories($dir, ['recursive' => false]) as $assetDir) {
            if (FileHelper::findFiles($assetDir) === []) {
                FileHelper::removeDirectory($assetDir);
            }
        }

        return $removed;
    }

    public function isExpired(string $path): bool
    {
        if (!file_exists($path)) {
            return true;
        }

        $ttl = Cloudinary::getInstance()->getSettings()->thumbnailCacheTtl;

        if ($ttl === 0) {
            return false;
        }

        return (time() - filemtime($path)) > $ttl;
    }

    public function getStats(): array
    {
        $dir = $this->getCacheDir();

        if (!is_dir($dir)) {
            return ['count' => 0, 'size' => 0];
        }

        $count = 0;
        $size = 0;

        foreach (FileHelper::findFiles($dir) as $file) {
            if (str_ends_with($file, '.pending')) {
                continue;
            }

            $count++;
            $size += filesize($file);
        }

        return ['count' => $count, 'size' => $size];
    }

    private function findCachedFile(int $assetId, int $width, int $height): ?string
    {
        $dir = $this->getAssetDir($assetId);

        if (!is_dir($dir)) {
            return null;
        }

        $pattern = $dir . DIRECTORY_SEPARATOR . "{$width}x{$height}.*";
        $matches = array_filter(
            glob($pattern) ?: [],
            fn($path) => !str_ends_with($path, '.pending'),
        );

        return $matches ? reset($matches) : null;
    }
}
