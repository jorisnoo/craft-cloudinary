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

    public function __construct(public ?VolumeFolder $folder)
    {
    }

    public function findFolder(mixed $criteria = []): ?VolumeFolder
    {
        $this->criteria = $criteria;

        return $this->folder;
    }
}

class RecordingAssetQuery extends AssetQuery
{
    public mixed $requestedVolumeId = null;
    public mixed $requestedFilename = null;
    public mixed $requestedFolderId = null;
    public mixed $requestedKind = null;
    public mixed $requestedLimit = null;

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

    public function kind(mixed $value): static
    {
        $this->requestedKind = $value;

        return $this;
    }

    public function limit($limit): static
    {
        $this->requestedLimit = $limit;

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

it('scopes root asset queries to the root folder id', function() {
    $asset = $this->createMock(Asset::class);
    [$action, $assets, $query] = queryTestAction(queryTestFolder(10, ''), [$asset]);

    expect($action->queryAsset('photo', null, AssetType::IMAGE))->toBe($asset)
        ->and($assets->criteria)->toBe(['volumeId' => 7, 'path' => ''])
        ->and($query->requestedVolumeId)->toBe(7)
        ->and($query->requestedFilename)->toBe('photo.*')
        ->and($query->requestedFolderId)->toBe(10)
        ->and($query->requestedKind)->toBe('image')
        ->and($query->requestedLimit)->toBe(2);
});

it('scopes nested asset queries to the exact nested folder id', function() {
    $asset = $this->createMock(Asset::class);
    [$action, $assets, $query] = queryTestAction(queryTestFolder(20, 'gallery/'), [$asset]);

    expect($action->queryAsset('photo', 'gallery', AssetType::IMAGE))->toBe($asset)
        ->and($assets->criteria)->toBe(['volumeId' => 7, 'path' => 'gallery/'])
        ->and($query->requestedFolderId)->toBe(20);
});

it('uses the video and audio kind constraint for video resources', function() {
    $asset = $this->createMock(Asset::class);
    [$action, , $query] = queryTestAction(queryTestFolder(20, 'media/'), [$asset]);

    expect($action->queryAsset('clip', 'media/', AssetType::VIDEO))->toBe($asset)
        ->and($query->requestedFilename)->toBe('clip.*')
        ->and($query->requestedKind)->toBe(['video', 'audio']);
});

it('matches raw resource filenames exactly without a kind constraint', function() {
    $asset = $this->createMock(Asset::class);
    [$action, , $query] = queryTestAction(queryTestFolder(30, 'docs/'), [$asset]);

    expect($action->queryAsset('document.pdf', 'docs', AssetType::RAW))->toBe($asset)
        ->and($query->requestedFilename)->toBe('document.pdf')
        ->and($query->requestedKind)->toBeNull();
});

it('refuses to choose between assets with different extensions', function() {
    $jpg = $this->createMock(Asset::class);
    $png = $this->createMock(Asset::class);
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
