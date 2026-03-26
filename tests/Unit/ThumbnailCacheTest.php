<?php

use Noo\CraftCloudinary\models\Settings;

describe('Settings', function() {
    it('has thumbnail cache disabled by default', function() {
        $settings = new Settings();
        expect($settings->enableThumbnailCache)->toBeFalse();
    });

    it('has a 7-day TTL by default', function() {
        $settings = new Settings();
        expect($settings->thumbnailCacheTtl)->toBe(604800);
    });

    it('defines validation rules', function() {
        $settings = new Settings();
        $rules = $settings->defineRules();

        expect($rules)->toBeArray();
        expect($rules)->not->toBeEmpty();
    });
});

describe('ThumbnailCache file operations', function() {
    $cacheDir = sys_get_temp_dir() . '/cloudinary-thumbs-test-' . getmypid();

    beforeEach(function() use ($cacheDir) {
        if (is_dir($cacheDir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($cacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
            }
            rmdir($cacheDir);
        }
    });

    it('creates cache files with correct path structure', function() use ($cacheDir) {
        $assetId = 42;
        $width = 200;
        $height = 150;
        $extension = 'jpg';

        $dir = $cacheDir . DIRECTORY_SEPARATOR . $assetId;
        mkdir($dir, 0755, true);
        $path = $dir . DIRECTORY_SEPARATOR . "{$width}x{$height}.{$extension}";
        file_put_contents($path, 'fake-image-data');

        expect(file_exists($path))->toBeTrue();
        expect(file_get_contents($path))->toBe('fake-image-data');
    });

    it('can glob for cached files regardless of extension', function() use ($cacheDir) {
        $assetId = 99;
        $dir = $cacheDir . DIRECTORY_SEPARATOR . $assetId;
        mkdir($dir, 0755, true);

        file_put_contents($dir . '/200x150.webp', 'data');

        $pattern = $dir . DIRECTORY_SEPARATOR . '200x150.*';
        $matches = glob($pattern);

        expect($matches)->toHaveCount(1);
        expect(basename($matches[0]))->toBe('200x150.webp');
    });

    it('invalidates an asset by removing its directory', function() use ($cacheDir) {
        $assetId = 77;
        $dir = $cacheDir . DIRECTORY_SEPARATOR . $assetId;
        mkdir($dir, 0755, true);

        file_put_contents($dir . '/100x100.jpg', 'data');
        file_put_contents($dir . '/200x200.jpg', 'data');

        expect(is_dir($dir))->toBeTrue();

        // Simulate invalidation: remove the directory
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        rmdir($dir);

        expect(is_dir($dir))->toBeFalse();
    });

    it('detects expired files based on modification time', function() use ($cacheDir) {
        $dir = $cacheDir . DIRECTORY_SEPARATOR . 'expiry-test';
        mkdir($dir, 0755, true);
        $path = $dir . '/100x100.jpg';
        file_put_contents($path, 'data');

        // File just created — should not be expired with a 7-day TTL
        $ttl = 604800;
        $age = time() - filemtime($path);
        expect($age < $ttl)->toBeTrue();

        // Backdate the file to simulate expiry
        touch($path, time() - $ttl - 1);
        clearstatcache(true, $path);
        $age = time() - filemtime($path);
        expect($age > $ttl)->toBeTrue();
    });
});
