<?php

namespace Noo\CraftCloudinary\fs;

use Cloudinary\Asset\AssetType;
use Cloudinary\Cloudinary;
use League\Flysystem\Config;
use League\Flysystem\UnableToGeneratePublicUrl;
use Noo\CraftCloudinary\helpers\ResourceTypes;
use ThomasVantuycom\FlysystemCloudinary\CloudinaryAdapter;
use Throwable;

/**
 * The stock adapter resolves public URLs through adminApi()->asset(), which
 * costs one rate-limited Admin API call per file - and readStream() goes
 * through publicUrl(), so every file read (image editor, copies, downloads)
 * paid that price. Delivery URLs can be built locally from the path alone.
 */
class LocalUrlAdapter extends CloudinaryAdapter
{
    public function __construct(private Cloudinary $cloudinary)
    {
        parent::__construct($cloudinary);
    }

    public function publicUrl(string $path, Config $config): string
    {
        try {
            // For image/video URLs the extension acts as the delivery format;
            // for raw files it is part of the public ID. Either way the URL
            // path component is the plain filename.
            $filename = basename($path);

            $resource = match (ResourceTypes::fromPath($path)) {
                AssetType::IMAGE => $this->cloudinary->image($filename),
                AssetType::VIDEO => $this->cloudinary->video($filename),
                default => $this->cloudinary->raw($filename),
            };

            return (string) $resource->toUrl();
        } catch (Throwable $e) {
            throw UnableToGeneratePublicUrl::dueToError($path, $e);
        }
    }
}
