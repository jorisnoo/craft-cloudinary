<?php

namespace Noo\CraftCloudinary\imagetransforms;

use craft\base\Component;
use craft\base\imagetransforms\ImageTransformerInterface;
use craft\elements\Asset;
use craft\models\ImageTransform;
use Noo\CraftCloudinary\behaviors\CloudinaryUrlBehavior;
use Noo\CraftCloudinary\Cloudinary;
use Noo\CraftCloudinary\fs\CloudinaryFs;
use Noo\CraftCloudinary\helpers\ImageTransforms;
use Noo\CraftCloudinary\helpers\PublicIds;
use Noo\CraftCloudinary\helpers\ResourceTypes;
use yii\base\InvalidConfigException;

class CloudinaryTransformer extends Component implements ImageTransformerInterface
{
    public function getTransformUrl(Asset $asset, ImageTransform $imageTransform, bool $immediately): string
    {
        $transform = [
            'width' => $imageTransform->width,
            'height' => $imageTransform->height,
            'crop' => ImageTransforms::mapModeToCrop($imageTransform->mode, $imageTransform->upscale),
            'gravity' => $this->_mapPositionToGravity($imageTransform->position),
            'flags' => $imageTransform->interlace !== 'none' ? 'progressive' : null,
            'quality' => $imageTransform->quality,
            'background' => $imageTransform->fill,
            'fetch_format' => $imageTransform->format ?? 'auto',
        ];

        $behavior = $asset->getBehavior('cloudinary:url');

        if (!$behavior instanceof CloudinaryUrlBehavior) {
            throw new InvalidConfigException("Asset {$asset->id} does not use a Cloudinary filesystem.");
        }

        return $behavior->getCloudinaryUrl($transform);
    }

    public function invalidateAssetTransforms(Asset $asset): void
    {
        $volume = $asset->getVolume();
        $fs = $volume->getFs();

        if (!$fs instanceof CloudinaryFs) {
            $fs = $volume->getTransformFs();
        }

        if (!$fs instanceof CloudinaryFs) {
            return;
        }

        try {
            $path = $asset->getPath();
            $fs->getClient()->uploadApi()->explicit(PublicIds::fromPath($path), [
                'type' => 'upload',
                'resource_type' => ResourceTypes::fromPath($path),
                'invalidate' => true,
            ]);
        } catch (\Throwable $e) {
            Cloudinary::log("Failed to invalidate transforms for asset {$asset->id}: {$e->getMessage()}", 'warning');
        }
    }

    private function _mapPositionToGravity(string $position): string
    {
        return match ($position) {
            'top-left' => 'north_west',
            'top-center' => 'north',
            'top-right' => 'north_east',
            'center-left' => 'west',
            'center-right' => 'east',
            'bottom-left' => 'south_west',
            'bottom-center' => 'south',
            'bottom-right' => 'south_east',
            default => 'center',
        };
    }
}
