<?php

namespace Noo\CraftCloudinary\helpers;

class AssetFolders
{
    /**
     * Converts an absolute Cloudinary asset folder into a path relative to the
     * volume subpath. Returns null when the folder lies outside the subpath.
     */
    public static function relativeToSubpath(string $assetFolder, string $volumeSubpath): ?string
    {
        $assetFolder = trim($assetFolder, '/.');
        $volumeSubpath = trim($volumeSubpath, '/.');

        if ($volumeSubpath === '') {
            return $assetFolder;
        }

        if ($assetFolder === $volumeSubpath) {
            return '';
        }

        $prefix = $volumeSubpath . '/';

        if (str_starts_with($assetFolder, $prefix)) {
            return substr($assetFolder, strlen($prefix));
        }

        return null;
    }
}
