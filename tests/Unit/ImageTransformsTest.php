<?php

use Noo\CraftCloudinary\helpers\ImageTransforms;

describe('mapModeToCrop', function() {
    it('maps fit with upscale to fit', function() {
        expect(ImageTransforms::mapModeToCrop('fit', true))->toBe('fit');
    });

    it('maps fit without upscale to limit', function() {
        expect(ImageTransforms::mapModeToCrop('fit', false))->toBe('limit');
    });

    it('maps letterbox with upscale to pad', function() {
        expect(ImageTransforms::mapModeToCrop('letterbox', true))->toBe('pad');
    });

    it('maps letterbox without upscale to lpad', function() {
        expect(ImageTransforms::mapModeToCrop('letterbox', false))->toBe('lpad');
    });

    it('maps stretch to scale regardless of upscale', function() {
        expect(ImageTransforms::mapModeToCrop('stretch', true))->toBe('scale');
        expect(ImageTransforms::mapModeToCrop('stretch', false))->toBe('scale');
    });

    it('maps crop to fill', function() {
        expect(ImageTransforms::mapModeToCrop('crop', true))->toBe('fill');
    });

    it('maps unknown modes to fill', function() {
        expect(ImageTransforms::mapModeToCrop('unknown', true))->toBe('fill');
    });
});

describe('isNativeTransform', function() {
    it('returns true for arrays with only native properties', function() {
        expect(ImageTransforms::isNativeTransform([
            'width' => 100,
            'height' => 200,
            'mode' => 'fit',
        ]))->toBeTrue();
    });

    it('returns false for arrays with custom Cloudinary properties', function() {
        expect(ImageTransforms::isNativeTransform([
            'width' => 100,
            'opacity' => 50,
        ]))->toBeFalse();
    });

    it('returns true for an empty array', function() {
        expect(ImageTransforms::isNativeTransform([]))->toBeTrue();
    });

    it('returns true for non-array values', function() {
        expect(ImageTransforms::isNativeTransform('thumb'))->toBeTrue();
        expect(ImageTransforms::isNativeTransform(null))->toBeTrue();
    });

    it('treats format as non-native to allow URL format override', function() {
        expect(ImageTransforms::isNativeTransform([
            'width' => 100,
            'format' => 'webp',
        ]))->toBeFalse();
    });

    it('recognizes all native properties', function() {
        $allNative = [
            'width' => 100,
            'height' => 200,
            'mode' => 'fit',
            'position' => 'center-center',
            'interlace' => 'line',
            'quality' => 80,
            'fill' => '#000000',
            'upscale' => true,
        ];

        expect(ImageTransforms::isNativeTransform($allNative))->toBeTrue();
    });
});
