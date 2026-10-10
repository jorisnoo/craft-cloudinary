<?php

use Cloudinary\Asset\AssetType;
use craft\elements\Asset;
use craft\elements\db\AssetQuery;
use craft\models\VolumeFolder;
use craft\services\Assets;
use Noo\CraftCloudinary\actions\BaseCloudinaryAction;

class QueryTestAction extends BaseCloudinaryAction
{
    public bool $loggedAmbiguity = false;

    public function __construct(
        int $volumeId,
        private readonly Assets $assets,
        private readonly AssetQuery $query,
    ) {
        parent::__construct($volumeId);
    }

    protected function assetsService(): Assets
    {
        return $this->assets;
    }

    protected function createAssetQuery(): AssetQuery
    {
        return $this->query;
    }

    protected function logAmbiguousAsset(string $publicId, string $path, string $resourceType): void
    {
        $this->loggedAmbiguity = true;
    }
}

class QueryTestAssets extends Assets
{
    public mixed $criteria = null;
    public ?int $rootFolderVolumeId = null;

    public function __construct(public ?VolumeFolder $folder)
    {
    }

    public function findFolder(mixed $criteria = []): ?VolumeFolder
    {
        $this->criteria = $criteria;

        return $this->folder;
    }

    public function getRootFolderByVolumeId(int $volumeId): ?VolumeFolder
    {
        $this->rootFolderVolumeId = $volumeId;

        return $this->folder;
    }
}

class RecordingAssetQuery extends AssetQuery
{
    public mixed $requestedVolumeId = null;
    public mixed $requestedFilename = null;
    public mixed $requestedFolderId = null;

    public function __construct(private readonly array $results)
    {
    }

    public function volumeId(mixed $value): static
    {
        $this->requestedVolumeId = $value;

        return $this;
    }

    public function filename(mixed $value): static
    {
        $this->requestedFilename = $value;

        return $this;
    }

    public function folderId(mixed $value): static
    {
        $this->requestedFolderId = $value;

        return $this;
    }

    public function all($db = null): array
    {
        return $this->results;
    }
}

function queryTestFolder(int $id, string $path): VolumeFolder
{
    return new VolumeFolder([
        'id' => $id,
        'volumeId' => 7,
        'path' => $path,
    ]);
}

function queryTestAction(
    VolumeFolder $folder,
    array $results,
): array {
    $assets = new QueryTestAssets($folder);
    $query = new RecordingAssetQuery($results);

    return [new QueryTestAction(7, $assets, $query), $assets, $query];
}

function queryTestAsset(string $filename): Asset
{
    $asset = test()->createMock(Asset::class);
    $asset->method('getFilename')->willReturn($filename);

    return $asset;
}

it('scopes root asset queries to the root folder id', function() {
    $asset = queryTestAsset('photo.jpg');
    [$action, $assets, $query] = queryTestAction(queryTestFolder(10, ''), [$asset]);

    expect($action->queryAsset('photo', null, AssetType::IMAGE))->toBe($asset)
        ->and($assets->rootFolderVolumeId)->toBe(7)
        ->and($assets->criteria)->toBeNull()
        ->and($query->requestedVolumeId)->toBe(7)
        ->and($query->requestedFilename)->toBe('photo.*')
        ->and($query->requestedFolderId)->toBe(10);
});

it('scopes nested asset queries to the exact nested folder id', function() {
    $asset = queryTestAsset('photo.jpg');
    [$action, $assets, $query] = queryTestAction(queryTestFolder(20, 'gallery/'), [$asset]);

    expect($action->queryAsset('photo', 'gallery', AssetType::IMAGE))->toBe($asset)
        ->and($assets->criteria)->toBe(['volumeId' => 7, 'path' => 'gallery/'])
        ->and($query->requestedFolderId)->toBe(20);
});

it('matches an extension that differs from the format Cloudinary reports', function() {
    $asset = queryTestAsset('photo.jpeg');
    [$action] = queryTestAction(queryTestFolder(10, ''), [$asset]);

    expect($action->queryAsset('photo', '', AssetType::IMAGE))->toBe($asset);
});

it('matches video resources by extension', function() {
    $asset = queryTestAsset('clip.mp4');
    [$action, , $query] = queryTestAction(queryTestFolder(20, 'media/'), [$asset]);

    expect($action->queryAsset('clip', 'media/', AssetType::VIDEO))->toBe($asset)
        ->and($query->requestedFilename)->toBe('clip.*');
});

it('ignores files stored under another resource type', function() {
    $image = queryTestAsset('photo.jpg');
    $zip = queryTestAsset('photo.zip');
    $uppercase = queryTestAsset('photo.JPG');
    [$action] = queryTestAction(queryTestFolder(10, ''), [$zip, $uppercase, $image]);

    expect($action->queryAsset('photo', '', AssetType::IMAGE))->toBe($image)
        ->and($action->loggedAmbiguity)->toBeFalse();
});

it('matches raw resource filenames exactly', function() {
    $asset = queryTestAsset('document.pdf');
    [$action, , $query] = queryTestAction(queryTestFolder(30, 'docs/'), [$asset]);

    // A PDF uploaded to Cloudinary as raw becomes a Craft asset named
    // document.pdf, which Craft would type as an image.
    expect($action->queryAsset('document.pdf', 'docs', AssetType::RAW))->toBe($asset)
        ->and($query->requestedFilename)->toBe('document.pdf');
});

it('matches raw resources', function() {
    $asset = queryTestAsset('data.zip');
    [$action] = queryTestAction(queryTestFolder(30, 'docs/'), [$asset]);

    expect($action->queryAsset('data.zip', 'docs', AssetType::RAW))->toBe($asset);
});

it('refuses to choose between assets with different extensions', function() {
    $jpg = queryTestAsset('photo.jpg');
    $png = queryTestAsset('photo.png');
    [$action] = queryTestAction(queryTestFolder(10, ''), [$jpg, $png]);

    expect($action->queryAsset('photo', '', AssetType::IMAGE))->toBeNull()
        ->and($action->loggedAmbiguity)->toBeTrue();
});

it('returns null without querying assets when the folder is unknown', function() {
    $assets = new QueryTestAssets(null);
    $query = new RecordingAssetQuery([]);
    $action = new QueryTestAction(7, $assets, $query);

    expect($action->queryAsset('photo', 'missing', AssetType::IMAGE))->toBeNull()
        ->and($assets->criteria)->toBe(['volumeId' => 7, 'path' => 'missing/'])
        ->and($query->requestedVolumeId)->toBeNull();
});
