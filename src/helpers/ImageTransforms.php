<?php

namespace Noo\CraftCloudinary\helpers;

use craft\models\ImageTransform;
use ReflectionClass;

class ImageTransforms
{
    public static function mapModeToCrop(string $mode, bool $upscale): string
    {
        return match ($mode) {
            'fit' => $upscale ? 'fit' : 'limit',
            'letterbox' => $upscale ? 'pad' : 'lpad',
            'stretch' => 'scale',
            default => 'fill',
        };
    }

    public static function isNativeTransform(mixed $transform): bool
    {
        if (is_array($transform)) {
            $nativeProperties = array_map(
                fn(\ReflectionProperty $property) => $property->getName(),
                (new ReflectionClass(ImageTransform::class))->getProperties(\ReflectionProperty::IS_PUBLIC),
            );

            // Exclude format so Cloudinary handles format conversion via URL
            $nativeProperties = array_diff($nativeProperties, ['format']);

            foreach ($transform as $key => $value) {
                if (!in_array($key, $nativeProperties, true)) {
                    return false;
                }
            }
        }

        return true;
    }
}
