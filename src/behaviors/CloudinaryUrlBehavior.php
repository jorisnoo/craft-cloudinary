<?php

namespace Noo\CraftCloudinary\behaviors;

use Cloudinary\Asset\AssetType;
use craft\elements\Asset;
use craft\events\DefineAssetUrlEvent;
use craft\events\GenerateTransformEvent;
use Noo\CraftCloudinary\fs\CloudinaryFs;
use Noo\CraftCloudinary\helpers\ImageTransforms;
use Noo\CraftCloudinary\helpers\ResourceTypes;
use Noo\CraftCloudinary\imagetransforms\CloudinaryTransformer;
use yii\base\Behavior;
use yii\base\InvalidConfigException;

class CloudinaryUrlBehavior extends Behavior
{
    /**
     * @var Asset
     */
    public $owner;

    public function events(): array
    {
        $volume = $this->owner->getVolume();
        $fs = $volume->getFs();
        $transformFs = $volume->getTransformFs();

        $events = [];

        if ($transformFs instanceof CloudinaryFs) {
            $events[Asset::EVENT_BEFORE_DEFINE_URL] = 'beforeDefineUrlForTransforms';
            $events[Asset::EVENT_BEFORE_GENERATE_TRANSFORM] = 'beforeGenerateTransform';
        }

        if ($fs instanceof CloudinaryFs) {
            $events[Asset::EVENT_DEFINE_URL] = 'defineUrl';
        }

        return $events;
    }

    public function beforeDefineUrlForTransforms(DefineAssetUrlEvent $event): void
    {
        $transform = $event->transform;

        if ($transform === null || ImageTransforms::isNativeTransform($transform)) {
            return;
        }

        $event->url = $this->getCloudinaryUrl($transform);
    }

    public function beforeGenerateTransform(GenerateTransformEvent $event): void
    {
        $event->transform->setTransformer(CloudinaryTransformer::class);
    }

    public function defineUrl(DefineAssetUrlEvent $event): void
    {
        if (!str_starts_with($event->url ?? '', 'https://res.cloudinary.com')) {
            $event->url = $this->getCloudinaryUrl();
        }
    }

    public function getCloudinaryUrl(?array $transform = null): string
    {
        $asset = $this->owner;

        if (isset($transform['mode'])) {
            $mode = $transform['mode'];
            $upscale = $transform['upscale'] ?? true;
            unset($transform['mode']);
            unset($transform['upscale']);

            $transform['crop'] = ImageTransforms::mapModeToCrop($mode, $upscale);
        }

        $volume = $asset->getVolume();
        $fs = $volume->getFs();
        $transformFs = $volume->getTransformFs();

        $hasCloudinaryFs = $fs instanceof CloudinaryFs;
        $fsWithConfig = $hasCloudinaryFs ? $fs : $transformFs;

        if ($hasCloudinaryFs) {
            // Public IDs should never contain the path in dynamic folder mode.
            $publicId = basename($asset->getPath());
        } else {
            $publicId = $asset->getUrl();

            if ($publicId === null || $publicId === '') {
                throw new InvalidConfigException('Cloudinary fetch transforms require the source filesystem to provide a public asset URL.');
            }
        }

        // Stored files must use the resource type the adapter uploaded them
        // as, which follows the extension rather than the MIME type (an
        // `.mpg` is uploaded as raw even though its MIME type is video).
        $resourceType = $hasCloudinaryFs
            ? ResourceTypes::fromPath($asset->getPath())
            : $this->_getResourceType();

        /**
         * @var CloudinaryFs $fsWithConfig
         */
        $client = $fsWithConfig->getClient();
        $resource = $client->{$resourceType}($publicId);

        if (!$hasCloudinaryFs) {
            $resource->deliveryType("fetch");
        }

        if ($transform !== null) {
            $resource->addTransformation($transform);

            if (isset($transform['format'])) {
                $resource->setFormat($transform['format']);
            }
        }

        return $resource->toUrl();
    }

    /**
     * Control panel thumbnail URL, or null when Cloudinary can't render the
     * asset as an image and Craft should show its file icon instead.
     */
    public function getCloudinaryThumbUrl(int $width, int $height): ?string
    {
        $volume = $this->owner->getVolume();
        $resourceType = $volume->getFs() instanceof CloudinaryFs
            ? ResourceTypes::fromPath($this->owner->getPath())
            : $this->_getResourceType();

        $transform = [
            'width' => $width,
            'height' => $height,
            'crop' => 'fill',
            'gravity' => 'auto',
            'quality' => 'auto',
        ];

        if ($resourceType === AssetType::IMAGE) {
            return $this->getCloudinaryUrl($transform + ['fetch_format' => 'auto']);
        }

        // Videos stored in Cloudinary get a frame as a JPEG. Fetch delivery
        // doesn't support video, and raw files can't be transformed.
        if ($resourceType === AssetType::VIDEO && $volume->getFs() instanceof CloudinaryFs) {
            return $this->getCloudinaryUrl($transform + ['format' => 'jpg']);
        }

        return null;
    }

    private function _getResourceType(): string
    {
        $mimeType = $this->owner->getMimeType();

        if ($mimeType === null) {
            return AssetType::RAW;
        }

        if ($mimeType === 'application/pdf' || str_starts_with($mimeType, 'image/')) {
            return AssetType::IMAGE;
        }

        if (str_starts_with($mimeType, "video/") || str_starts_with($mimeType, "audio/")) {
            return AssetType::VIDEO;
        }

        return AssetType::RAW;
    }
}
