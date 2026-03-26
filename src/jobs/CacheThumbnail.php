<?php

namespace Noo\CraftCloudinary\jobs;

use craft\queue\BaseJob;
use Noo\CraftCloudinary\Cloudinary;

class CacheThumbnail extends BaseJob
{
    public function __construct(
        public int $assetId,
        public int $width,
        public int $height,
        public string $cloudinaryUrl,
    ) {
        parent::__construct();
    }

    public function getDescription(): string
    {
        return "Caching Cloudinary thumbnail for asset {$this->assetId}";
    }

    public function execute($queue): void
    {
        $cache = Cloudinary::getInstance()->thumbnailCache;

        try {
            $contents = @file_get_contents($this->cloudinaryUrl);

            if ($contents === false) {
                Cloudinary::log("Failed to cache thumbnail from: {$this->cloudinaryUrl}", 'error');
                return;
            }

            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($contents);

            $extension = match ($mimeType) {
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                'image/avif' => 'avif',
                'image/svg+xml' => 'svg',
                default => 'jpg',
            };

            $cache->put(
                $this->assetId,
                $this->width,
                $this->height,
                $contents,
                $extension,
            );
        } finally {
            $cache->clearPending($this->assetId, $this->width, $this->height);
        }
    }
}
