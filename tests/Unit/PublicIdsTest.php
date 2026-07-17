<?php

use Cloudinary\Asset\AssetType;
use Noo\CraftCloudinary\helpers\PublicIds;
use Noo\CraftCloudinary\helpers\ResourceTypes;

describe('ResourceTypes::fromPath', function() {
    it('types common extensions like the adapter does', function() {
        expect(ResourceTypes::fromPath('photos/hero.jpg'))->toBe(AssetType::IMAGE);
        expect(ResourceTypes::fromPath('file.pdf'))->toBe(AssetType::IMAGE);
        expect(ResourceTypes::fromPath('clips/intro.mp4'))->toBe(AssetType::VIDEO);
        expect(ResourceTypes::fromPath('audio/song.mp3'))->toBe(AssetType::VIDEO);
        expect(ResourceTypes::fromPath('docs/data.zip'))->toBe(AssetType::RAW);
        expect(ResourceTypes::fromPath('README'))->toBe(AssetType::RAW);
    });

    it('is case-sensitive, matching the adapter', function() {
        expect(ResourceTypes::fromPath('photo.JPG'))->toBe(AssetType::RAW);
    });
});

describe('PublicIds::fromPath', function() {
    it('strips the extension for image and video files', function() {
        expect(PublicIds::fromPath('photos/hero.jpg'))->toBe('hero');
        expect(PublicIds::fromPath('clips/intro.mp4'))->toBe('intro');
    });

    it('keeps the extension for raw files', function() {
        expect(PublicIds::fromPath('docs/data.zip'))->toBe('data.zip');
    });

    it('never includes folders', function() {
        expect(PublicIds::fromPath('a/b/c/photo.png'))->toBe('photo');
        expect(PublicIds::fromPath('a/b/c/archive.tar'))->toBe('archive.tar');
    });
});
