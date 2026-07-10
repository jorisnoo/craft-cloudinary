<?php

use Cloudinary\Cloudinary as CloudinaryClient;
use craft\base\FsInterface;
use craft\elements\Asset;
use craft\models\Volume;
use Noo\CraftCloudinary\behaviors\CloudinaryUrlBehavior;
use Noo\CraftCloudinary\fs\CloudinaryFs;
use yii\base\InvalidConfigException;

function urlBehaviorCloudinaryClient(): CloudinaryClient
{
    return new CloudinaryClient([
        'cloud' => ['cloud_name' => 'demo'],
        'url' => [
            'analytics' => false,
            'forceVersion' => false,
        ],
    ]);
}

class UrlBehaviorCloudinaryFs extends CloudinaryFs
{
    public function __construct(private readonly CloudinaryClient $client)
    {
    }

    public function getClient(): CloudinaryClient
    {
        return $this->client;
    }
}

class UrlBehaviorVolume extends Volume
{
    public function __construct(
        private readonly FsInterface $fs,
        private readonly FsInterface $transformFs,
    ) {
    }

    public function getFs(): FsInterface
    {
        return $this->fs;
    }

    public function getTransformFs(): FsInterface
    {
        return $this->transformFs;
    }
}

class UrlBehaviorAsset extends Asset
{
    public function __construct(
        private readonly Volume $testVolume,
        private readonly ?string $testUrl,
        private readonly string $testPath,
        private readonly ?string $testMimeType = 'image/jpeg',
    ) {
    }

    public function getVolume(): Volume
    {
        return $this->testVolume;
    }

    public function getUrl(mixed $transform = null, ?bool $immediately = null): ?string
    {
        return $this->testUrl;
    }

    public function getPath(?string $filename = null): string
    {
        return $this->testPath;
    }

    public function getMimeType(mixed $transform = null): ?string
    {
        return $this->testMimeType;
    }
}

function urlBehaviorCloudinaryFs(): CloudinaryFs
{
    return new UrlBehaviorCloudinaryFs(urlBehaviorCloudinaryClient());
}

function urlBehaviorAsset(
    FsInterface $fs,
    FsInterface $transformFs,
    ?string $url,
    string $path,
    ?string $mimeType = 'image/jpeg',
): Asset {
    return new UrlBehaviorAsset(
        new UrlBehaviorVolume($fs, $transformFs),
        $url,
        $path,
        $mimeType,
    );
}

it('keeps the complete remote URL for transform-only fetch delivery', function() {
    $sourceFs = $this->createMock(FsInterface::class);
    $cloudinaryFs = urlBehaviorCloudinaryFs();
    $sourceUrl = 'https://example.com/images/foo.jpg';
    $asset = urlBehaviorAsset($sourceFs, $cloudinaryFs, $sourceUrl, 'images/foo.jpg');
    $behavior = new CloudinaryUrlBehavior();
    $behavior->owner = $asset;

    $url = $behavior->getCloudinaryUrl(['width' => 100, 'format' => 'webp']);

    expect($url)->toContain('/image/fetch/')
        ->and($url)->toContain('w_100')
        ->and($url)->toContain('webp')
        ->and($url)->toContain($sourceUrl);
});

it('uses only the basename for assets stored in Cloudinary', function() {
    $cloudinaryFs = urlBehaviorCloudinaryFs();
    $asset = urlBehaviorAsset(
        $cloudinaryFs,
        $cloudinaryFs,
        'https://res.cloudinary.com/demo/image/upload/foo.jpg',
        'gallery/foo.jpg',
    );
    $behavior = new CloudinaryUrlBehavior();
    $behavior->owner = $asset;

    $url = $behavior->getCloudinaryUrl();

    expect($url)->toContain('/image/upload/foo.jpg')
        ->and($url)->not->toContain('/gallery/foo.jpg');
});

it('rejects transform-only assets without a public source URL', function() {
    $sourceFs = $this->createMock(FsInterface::class);
    $cloudinaryFs = urlBehaviorCloudinaryFs();
    $asset = urlBehaviorAsset($sourceFs, $cloudinaryFs, null, 'images/foo.jpg');
    $behavior = new CloudinaryUrlBehavior();
    $behavior->owner = $asset;

    expect(fn() => $behavior->getCloudinaryUrl())
        ->toThrow(InvalidConfigException::class, 'Cloudinary fetch transforms require the source filesystem to provide a public asset URL.');
});
