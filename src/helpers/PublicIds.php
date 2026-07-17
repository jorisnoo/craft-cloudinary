<?php

namespace Noo\CraftCloudinary\helpers;

use Cloudinary\Asset\AssetType;

class PublicIds
{
    /**
     * Derives the Cloudinary public ID from a filesystem path in dynamic
     * folders mode: folders never appear in the public ID, and raw files
     * keep their extension while image/video public IDs drop it.
     */
    public static function fromPath(string $path): string
    {
        $basename = basename($path);

        if (ResourceTypes::fromPath($path) === AssetType::RAW) {
            return $basename;
        }

        return pathinfo($basename, PATHINFO_FILENAME);
    }
}
