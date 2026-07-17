<?php

use Cloudinary\Cloudinary;
use League\Flysystem\Config;
use Noo\CraftCloudinary\fs\LocalUrlAdapter;

describe('LocalUrlAdapter::publicUrl', function() {
    beforeEach(function() {
        // Same client configuration as CloudinaryFs::getClient()
        $this->adapter = new LocalUrlAdapter(new Cloudinary([
            'cloud' => [
                'cloud_name' => 'demo',
                'api_key' => 'key',
                'api_secret' => 'secret',
            ],
            'url' => [
                'analytics' => false,
                'forceVersion' => false,
            ],
        ]));
    });

    it('builds image URLs locally', function() {
        expect($this->adapter->publicUrl('photos/hero.jpg', new Config()))
            ->toBe('https://res.cloudinary.com/demo/image/upload/hero.jpg');
    });

    it('builds video URLs locally', function() {
        expect($this->adapter->publicUrl('clips/intro.mp4', new Config()))
            ->toBe('https://res.cloudinary.com/demo/video/upload/intro.mp4');
    });

    it('builds raw URLs locally, keeping the extension in the public ID', function() {
        expect($this->adapter->publicUrl('docs/data.zip', new Config()))
            ->toBe('https://res.cloudinary.com/demo/raw/upload/data.zip');
    });

    it('serves audio through the video resource type', function() {
        expect($this->adapter->publicUrl('song.mp3', new Config()))
            ->toBe('https://res.cloudinary.com/demo/video/upload/song.mp3');
    });
});
