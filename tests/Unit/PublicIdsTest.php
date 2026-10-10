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

describe('PublicIds::keyFromResource', function() {
    it('keys resources like the Craft assets made from them', function() {
        expect(PublicIds::keyFromResource(AssetType::IMAGE, 'photo', 'jpg'))->toBe('image:photo');
        expect(PublicIds::keyFromResource(AssetType::IMAGE, 'photo'))->toBe('image:photo');
        expect(PublicIds::keyFromResource(AssetType::RAW, 'data.zip'))->toBe('raw:data.zip');
        expect(PublicIds::keyFromResource(AssetType::RAW, 'document.pdf'))->toBe('image:document');
    });
});

describe('PublicIds::key', function() {
    it('identifies assets by resource type and public ID', function() {
        expect(PublicIds::key(AssetType::IMAGE, 'photo'))->toBe('image:photo');
        expect(PublicIds::key(AssetType::RAW, 'legacy/path/data.zip'))->toBe('raw:data.zip');
    });

    it('gives a path the key of the asset it is stored as', function() {
        expect(PublicIds::keyFromPath('a/photo.jpeg'))->toBe('image:photo');
        expect(PublicIds::keyFromPath('a/photo.jpg'))->toBe('image:photo');
        expect(PublicIds::keyFromPath('clip.mp4'))->toBe('video:clip');
        expect(PublicIds::keyFromPath('data.zip'))->toBe('raw:data.zip');
        expect(PublicIds::keyFromPath('scan.JPG'))->toBe('raw:scan.JPG');
    });
});
