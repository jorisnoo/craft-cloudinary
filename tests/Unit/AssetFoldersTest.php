<?php

use Noo\CraftCloudinary\helpers\AssetFolders;

describe('AssetFolders::relativeToSubpath', function() {
    it('returns the folder unchanged when the volume has no subpath', function() {
        expect(AssetFolders::relativeToSubpath('photos/nested', ''))->toBe('photos/nested')
            ->and(AssetFolders::relativeToSubpath('', ''))->toBe('');
    });

    it('strips the subpath prefix from folders inside it', function() {
        expect(AssetFolders::relativeToSubpath('volume-a/photos', 'volume-a'))->toBe('photos')
            ->and(AssetFolders::relativeToSubpath('volume-a/photos/nested', 'volume-a'))->toBe('photos/nested');
    });

    it('maps the subpath folder itself to the volume root', function() {
        expect(AssetFolders::relativeToSubpath('volume-a', 'volume-a'))->toBe('');
    });

    it('returns null for folders outside the subpath', function() {
        expect(AssetFolders::relativeToSubpath('elsewhere/photos', 'volume-a'))->toBeNull()
            ->and(AssetFolders::relativeToSubpath('', 'volume-a'))->toBeNull()
            ->and(AssetFolders::relativeToSubpath('volume-abc', 'volume-a'))->toBeNull();
    });

    it('normalizes slashes on both sides without changing folder dots', function() {
        expect(AssetFolders::relativeToSubpath('/volume-a/photos/', '/volume-a/'))->toBe('photos')
            ->and(AssetFolders::relativeToSubpath('.volume-a/photos.', '.volume-a'))->toBe('photos.');
    });

    it('supports nested subpaths', function() {
        expect(AssetFolders::relativeToSubpath('sites/a/photos', 'sites/a'))->toBe('photos')
            ->and(AssetFolders::relativeToSubpath('sites/b/photos', 'sites/a'))->toBeNull();
    });
});

describe('AssetFolders::searchExpression', function() {
    it('searches all uploaded assets when the volume has no subpath', function() {
        expect(AssetFolders::searchExpression(''))->toBe('type:upload');
    });

    it('searches the exact volume subpath and all descendants', function() {
        expect(AssetFolders::searchExpression('/sites/volume-a/'))
            ->toBe('type:upload AND (asset_folder=sites/volume-a OR asset_folder=sites/volume-a/*)');
    });

    it('escapes reserved characters in volume subpaths', function() {
        expect(AssetFolders::searchExpression('sites/my volume'))
            ->toBe('type:upload AND (asset_folder=sites/my\\ volume OR asset_folder=sites/my\\ volume/*)');
    });
});
