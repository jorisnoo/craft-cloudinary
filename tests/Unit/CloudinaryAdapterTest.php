<?php

use Cloudinary\Cloudinary;
use League\Flysystem\Config;
use Noo\CraftCloudinary\fs\CloudinaryAdapter;

describe('CloudinaryAdapter::publicUrl', function() {
    beforeEach(function() {
        // Same client configuration as CloudinaryFs::getClient()
        $this->adapter = new CloudinaryAdapter(new Cloudinary([
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

class RecordingUploadApi extends \Cloudinary\Api\Upload\UploadApi
{
    public array $calls = [];
    public array $uploadResponse = [];

    public function __construct()
    {
    }

    public function upload(mixed $file, array $options = []): \Cloudinary\Api\ApiResponse
    {
        $this->calls[] = ['upload', $file, $options];

        return new \Cloudinary\Api\ApiResponse($this->uploadResponse, []);
    }

    public function rename(string $fromPublicId, string $toPublicId, array $options = []): mixed
    {
        $this->calls[] = ['rename', $fromPublicId, $toPublicId, $options];

        return null;
    }
}

class RecordingAdminApi extends \Cloudinary\Api\Admin\AdminApi
{
    public array $calls = [];

    public function __construct()
    {
    }

    public function update(array|string $publicId, array $options = []): \Cloudinary\Api\ApiResponse
    {
        $this->calls[] = ['update', $publicId, $options];

        return new \Cloudinary\Api\ApiResponse([], []);
    }
}

class RecordingCloudinary extends Cloudinary
{
    public RecordingUploadApi $upload;
    public RecordingAdminApi $admin;

    public function __construct()
    {
        parent::__construct([
            'cloud' => ['cloud_name' => 'demo', 'api_key' => 'key', 'api_secret' => 'secret'],
            'url' => ['analytics' => false, 'forceVersion' => false],
        ]);

        $this->upload = new RecordingUploadApi();
        $this->admin = new RecordingAdminApi();
    }

    public function uploadApi(): \Cloudinary\Api\Upload\UploadApi
    {
        return $this->upload;
    }

    public function adminApi(): \Cloudinary\Api\Admin\AdminApi
    {
        return $this->admin;
    }
}

describe('CloudinaryAdapter file operations', function() {
    beforeEach(function() {
        $this->client = new RecordingCloudinary();
        $this->adapter = new CloudinaryAdapter($this->client);
    });

    it('uploads into the asset folder with a folder-less public ID', function() {
        $this->adapter->write('photos/hero.jpg', 'contents', new Config());

        [$method, , $options] = $this->client->upload->calls[0];

        expect($method)->toBe('upload')
            ->and($options['public_id'])->toBe('hero')
            ->and($options['resource_type'])->toBe('image')
            ->and($options['asset_folder'])->toBe('photos')
            ->and($options['overwrite'])->toBeTrue();
    });

    it('updates the asset folder when only the folder changes, including moves to the root', function() {
        $this->adapter->move('photos/hero.jpg', 'hero.jpg', new Config());

        expect($this->client->admin->calls[0][0])->toBe('update')
            ->and($this->client->admin->calls[0][1])->toBe('hero')
            ->and($this->client->admin->calls[0][2]['asset_folder'])->toBe('');
    });

    it('renames without overwriting an existing asset', function() {
        $this->adapter->move('photos/hero.jpg', 'archive/banner.jpg', new Config());

        [$method, $from, $to, $options] = $this->client->upload->calls[0];

        expect($method)->toBe('rename')
            ->and($from)->toBe('hero')
            ->and($to)->toBe('banner')
            ->and($options['asset_folder'])->toBe('archive')
            ->and($options['overwrite'])->toBeFalse();
    });

    it('copies by letting Cloudinary fetch the delivery URL', function() {
        $this->adapter->copy('photos/hero.jpg', 'archive/hero-copy.jpg', new Config());

        [$method, $file, $options] = $this->client->upload->calls[0];

        expect($method)->toBe('upload')
            ->and($file)->toBe('https://res.cloudinary.com/demo/image/upload/hero.jpg')
            ->and($options['public_id'])->toBe('hero-copy')
            ->and($options['asset_folder'])->toBe('archive')
            ->and($options['overwrite'])->toBeFalse();
    });

    it('fails a copy that would replace an existing asset', function() {
        $this->client->upload->uploadResponse = ['existing' => true];

        $this->adapter->copy('photos/hero.jpg', 'archive/hero-copy.jpg', new Config());
    })->throws(\League\Flysystem\UnableToCopyFile::class);
});
