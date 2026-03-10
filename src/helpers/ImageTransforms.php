<?php

namespace Noo\CraftCloudinary\helpers;

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
            $nativeProperties = [
                'width',
                'height',
                //'format', // allow changing the format in the url
                'mode',
                'position',
                'interlace',
                'quality',
                'fill',
                'upscale',
            ];

            foreach ($transform as $key => $value) {
                if (!in_array($key, $nativeProperties, true)) {
                    return false;
                }
            }
        }

        return true;
    }
}
