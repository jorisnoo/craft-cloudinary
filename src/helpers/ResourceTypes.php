<?php

namespace Noo\CraftCloudinary\helpers;

use Cloudinary\Asset\AssetType;

class ResourceTypes
{
    /**
     * Maps a filesystem path to the Cloudinary resource type the adapter
     * uploads it as. Mirrors CloudinaryAdapter::resourceType(), which is
     * private - the mapping must stay identical, because API calls and
     * delivery URLs for an asset only work with the resource type it was
     * uploaded under.
     */
    private const EXTENSION_MAP = [
        // Image formats
        '3ds' => AssetType::IMAGE,
        'ai' => AssetType::IMAGE,
        'arw' => AssetType::IMAGE,
        'avif' => AssetType::IMAGE,
        'bmp' => AssetType::IMAGE,
        'bw' => AssetType::IMAGE,
        'cr2' => AssetType::IMAGE,
        'cr3' => AssetType::IMAGE,
        'djvu' => AssetType::IMAGE,
        'dng' => AssetType::IMAGE,
        'eps' => AssetType::IMAGE,
        'eps3' => AssetType::IMAGE,
        'ept' => AssetType::IMAGE,
        'fbx' => AssetType::IMAGE,
        'flif' => AssetType::IMAGE,
        'gif' => AssetType::IMAGE,
        'glb' => AssetType::IMAGE,
        'gltf' => AssetType::IMAGE,
        'hdp' => AssetType::IMAGE,
        'heic' => AssetType::IMAGE,
        'heif' => AssetType::IMAGE,
        'ico' => AssetType::IMAGE,
        'indd' => AssetType::IMAGE,
        'jp2' => AssetType::IMAGE,
        'jpe' => AssetType::IMAGE,
        'jpeg' => AssetType::IMAGE,
        'jpg' => AssetType::IMAGE,
        'jxl' => AssetType::IMAGE,
        'jxr' => AssetType::IMAGE,
        'obj' => AssetType::IMAGE,
        'pdf' => AssetType::IMAGE,
        'ply' => AssetType::IMAGE,
        'png' => AssetType::IMAGE,
        'ps' => AssetType::IMAGE,
        'psd' => AssetType::IMAGE,
        'svg' => AssetType::IMAGE,
        'tga' => AssetType::IMAGE,
        'tif' => AssetType::IMAGE,
        'tiff' => AssetType::IMAGE,
        'u3ma' => AssetType::IMAGE,
        'usdz' => AssetType::IMAGE,
        'wdp' => AssetType::IMAGE,
        'webp' => AssetType::IMAGE,

        // Video formats
        '3g2' => AssetType::VIDEO,
        '3gp' => AssetType::VIDEO,
        'avi' => AssetType::VIDEO,
        'flv' => AssetType::VIDEO,
        'm2ts' => AssetType::VIDEO,
        'mkv' => AssetType::VIDEO,
        'mov' => AssetType::VIDEO,
        'mp4' => AssetType::VIDEO,
        'mpeg' => AssetType::VIDEO,
        'mts' => AssetType::VIDEO,
        'mxf' => AssetType::VIDEO,
        'ogv' => AssetType::VIDEO,
        'ts' => AssetType::VIDEO,
        'webm' => AssetType::VIDEO,
        'wmv' => AssetType::VIDEO,

        // Audio formats (delivered under the video resource type)
        'aac' => AssetType::VIDEO,
        'aiff' => AssetType::VIDEO,
        'amr' => AssetType::VIDEO,
        'flac' => AssetType::VIDEO,
        'm4a' => AssetType::VIDEO,
        'mp3' => AssetType::VIDEO,
        'ogg' => AssetType::VIDEO,
        'opus' => AssetType::VIDEO,
        'wav' => AssetType::VIDEO,
    ];

    public static function fromPath(string $path): string
    {
        // Case-sensitive on purpose: the adapter's lookup is too, so an
        // uppercase extension is uploaded as raw and must be typed as raw.
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return self::EXTENSION_MAP[$extension] ?? AssetType::RAW;
    }
}
