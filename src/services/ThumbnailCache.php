<?php

namespace Noo\CraftCloudinary\services;

use Craft;
use craft\helpers\FileHelper;
use Noo\CraftCloudinary\Cloudinary;
use yii\base\Component;

class ThumbnailCache extends Component
{
    public function getCacheDir(): string
    {
        return Craft::getAlias('@storage/runtime/cloudinary-thumbs');
    }

    public function has(int $assetId, int $width, int $height): bool
    {
        $path = $this->findCachedFile($assetId, $width, $height);

        return $path !== null && !$this->isExpired($path);
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
        $dir = $this->getCacheDir() . DIRECTORY_SEPARATOR . $assetId;
        FileHelper::createDirectory($dir);

        $path = $dir . DIRECTORY_SEPARATOR . "{$width}x{$height}.{$extension}";
        file_put_contents($path, $contents);

        return $path;
    }

    public function invalidateAsset(int $assetId): void
    {
        $dir = $this->getCacheDir() . DIRECTORY_SEPARATOR . $assetId;

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

    private function findCachedFile(int $assetId, int $width, int $height): ?string
    {
        $dir = $this->getCacheDir() . DIRECTORY_SEPARATOR . $assetId;

        if (!is_dir($dir)) {
            return null;
        }

        $pattern = $dir . DIRECTORY_SEPARATOR . "{$width}x{$height}.*";
        $matches = glob($pattern);

        return $matches ? $matches[0] : null;
    }
}
