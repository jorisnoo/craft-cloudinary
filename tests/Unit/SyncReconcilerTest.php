<?php

use Noo\CraftCloudinary\services\SyncReconciler;

describe('SyncReconciler resource key building', function() {
    // Use reflection to test the private buildResourceKey method
    beforeEach(function() {
        $this->reconciler = new SyncReconciler();
        $this->method = new ReflectionMethod(SyncReconciler::class, 'buildResourceKey');
    });

    it('builds key for image with folder', function() {
        $resource = [
            'public_id' => 'hero-banner',
            'resource_type' => 'image',
            'format' => 'jpg',
            'asset_folder' => 'photos',
        ];

        $key = $this->method->invoke($this->reconciler, $resource);

        expect($key)->toBe('photos/hero-banner.jpg');
    });

    it('builds key for image without folder', function() {
        $resource = [
            'public_id' => 'logo',
            'resource_type' => 'image',
            'format' => 'png',
            'asset_folder' => '',
        ];

        $key = $this->method->invoke($this->reconciler, $resource);

        expect($key)->toBe('logo.png');
    });

    it('builds key for raw resource without format extension', function() {
        $resource = [
            'public_id' => 'document.pdf',
            'resource_type' => 'raw',
            'format' => 'pdf',
            'asset_folder' => 'docs',
        ];

        $key = $this->method->invoke($this->reconciler, $resource);

        expect($key)->toBe('docs/document.pdf');
    });

    it('builds key for video with nested folder', function() {
        $resource = [
            'public_id' => 'intro',
            'resource_type' => 'video',
            'format' => 'mp4',
            'asset_folder' => 'media/videos',
        ];

        $key = $this->method->invoke($this->reconciler, $resource);

        expect($key)->toBe('media/videos/intro.mp4');
    });

    it('strips leading/trailing slashes from folder path', function() {
        $resource = [
            'public_id' => 'test',
            'resource_type' => 'image',
            'format' => 'webp',
            'asset_folder' => '/photos/',
        ];

        $key = $this->method->invoke($this->reconciler, $resource);

        expect($key)->toBe('photos/test.webp');
    });

    it('uses basename when public_id contains path', function() {
        $resource = [
            'public_id' => 'old/path/image',
            'resource_type' => 'image',
            'format' => 'jpg',
            'asset_folder' => '',
        ];

        $key = $this->method->invoke($this->reconciler, $resource);

        expect($key)->toBe('image.jpg');
    });
});

describe('SyncReconciler metadata change detection', function() {
    beforeEach(function() {
        $this->reconciler = new SyncReconciler();
        $this->method = new ReflectionMethod(SyncReconciler::class, 'hasMetadataChanged');
    });

    it('detects size changes', function() {
        $craft = ['size' => '1024', 'width' => '800', 'height' => '600'];
        $cloudinary = ['resource_type' => 'image', 'bytes' => 2048, 'width' => 800, 'height' => 600];

        expect($this->method->invoke($this->reconciler, $craft, $cloudinary))->toBeTrue();
    });

    it('detects width changes for images', function() {
        $craft = ['size' => '1024', 'width' => '800', 'height' => '600'];
        $cloudinary = ['resource_type' => 'image', 'bytes' => 1024, 'width' => 1600, 'height' => 600];

        expect($this->method->invoke($this->reconciler, $craft, $cloudinary))->toBeTrue();
    });

    it('detects height changes for images', function() {
        $craft = ['size' => '1024', 'width' => '800', 'height' => '600'];
        $cloudinary = ['resource_type' => 'image', 'bytes' => 1024, 'width' => 800, 'height' => 1200];

        expect($this->method->invoke($this->reconciler, $craft, $cloudinary))->toBeTrue();
    });

    it('ignores width/height changes for non-image resources', function() {
        $craft = ['size' => '1024', 'width' => null, 'height' => null];
        $cloudinary = ['resource_type' => 'video', 'bytes' => 1024, 'width' => 1920, 'height' => 1080];

        expect($this->method->invoke($this->reconciler, $craft, $cloudinary))->toBeFalse();
    });

    it('returns false when nothing changed', function() {
        $craft = ['size' => '1024', 'width' => '800', 'height' => '600'];
        $cloudinary = ['resource_type' => 'image', 'bytes' => 1024, 'width' => 800, 'height' => 600];

        expect($this->method->invoke($this->reconciler, $craft, $cloudinary))->toBeFalse();
    });

    it('handles null cloudinary values', function() {
        $craft = ['size' => '1024', 'width' => '800', 'height' => '600'];
        $cloudinary = [];

        expect($this->method->invoke($this->reconciler, $craft, $cloudinary))->toBeFalse();
    });
});
