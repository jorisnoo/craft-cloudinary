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

    /**
     * Identifies a Cloudinary asset by resource type and public ID.
     *
     * Craft filenames can't be rebuilt from a Cloudinary resource: Cloudinary
     * reports the detected format rather than the uploaded extension (a
     * `.jpeg` upload has format `jpg`). Comparing these keys instead matches
     * the asset the adapter actually uploaded the file as.
     */
    public static function key(string $resourceType, string $publicId): string
    {
        return $resourceType . ':' . basename($publicId);
    }

    /**
     * The key of a Cloudinary resource, as seen by Craft.
     *
     * Craft names assets after the resource (public ID plus format) and maps
     * names back to resource types by extension, so a resource Craft can't
     * represent faithfully - such as `document.pdf` uploaded as raw - must
     * get the key of the Craft asset made from it, or it would never match.
     *
     * @param ?string $format omitted when unknown, as in most webhooks
     */
    public static function keyFromResource(string $resourceType, string $publicId, ?string $format = null): string
    {
        if ($resourceType === AssetType::RAW) {
            return self::keyFromPath(basename($publicId));
        }

        if ($format !== null && $format !== '') {
            return self::keyFromPath(basename($publicId) . ".{$format}");
        }

        return self::key($resourceType, $publicId);
    }

    /**
     * The key of the Cloudinary asset a filesystem path is stored as.
     */
    public static function keyFromPath(string $path): string
    {
        return self::key(ResourceTypes::fromPath($path), self::fromPath($path));
    }
}
