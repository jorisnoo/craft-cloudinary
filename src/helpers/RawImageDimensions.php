<?php

namespace Noo\CraftCloudinary\helpers;

use Craft;
use GuzzleHttp\ClientInterface;
use Noo\CraftCloudinary\Cloudinary;

/**
 * Cloudinary doesn't report dimensions for raw files, such as images with an
 * uppercase extension (see ResourceTypes::fromPath()). Their dimensions are
 * read from the start of the file instead, without downloading all of it.
 */
class RawImageDimensions
{
    // Image headers sit at the start of the file. In a JPEG they follow the
    // EXIF data, which can include an embedded preview.
    private const RANGE_BYTES = 262144;

    // Formats getimagesize() can read, so files it can't (like SVG) don't
    // cost a request on every sync.
    private const READABLE_EXTENSIONS = ['avif', 'bmp', 'gif', 'ico', 'jpe', 'jpeg', 'jpg', 'png', 'psd', 'tif', 'tiff', 'webp'];

    public static function isReadable(string $filename): bool
    {
        return in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), self::READABLE_EXTENSIONS, true);
    }

    /**
     * @return array{int, int}|null width and height
     */
    public static function fetch(string $url, ?ClientInterface $httpClient = null): ?array
    {
        try {
            $data = (string) ($httpClient ?? Craft::createGuzzleClient())
                ->request('GET', $url, [
                    'headers' => ['Range' => 'bytes=0-' . (self::RANGE_BYTES - 1)],
                    'timeout' => 10,
                ])
                ->getBody();
        } catch (\Throwable $e) {
            Cloudinary::log("Could not read image dimensions from {$url}: {$e->getMessage()}", 'warning');
            return null;
        }

        $size = @getimagesizefromstring($data);

        if ($size === false || $size[0] <= 0 || $size[1] <= 0) {
            Cloudinary::log("Could not read image dimensions from {$url}", 'warning');
            return null;
        }

        return [$size[0], $size[1]];
    }
}
