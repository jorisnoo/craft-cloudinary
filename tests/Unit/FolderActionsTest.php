<?php

use craft\models\Volume;
use craft\models\VolumeFolder;
use craft\services\Assets;
use craft\services\Volumes;
use Noo\CraftCloudinary\actions\FolderCreateAction;
use Noo\CraftCloudinary\actions\FolderDeleteAction;
use Noo\CraftCloudinary\actions\FolderRenameAction;

class FolderCreateTestAction extends FolderCreateAction
{
    public function __construct(
        int $volumeId,
        private readonly Assets $assets,
        private readonly Volumes $volumes,
    ) {
        parent::__construct($volumeId);
    }

    protected function assetsService(): Assets
    {
        return $this->assets;
    }

    protected function volumesService(): Volumes
    {
        return $this->volumes;
    }
}

class FolderDeleteTestAction extends FolderDeleteAction
{
    public function __construct(int $volumeId, private readonly Assets $assets)
    {
        parent::__construct($volumeId);
    }

    protected function assetsService(): Assets
    {
        return $this->assets;
    }
}

class FolderRenameTestAction extends FolderRenameAction
{
    public ?string $createdFolderPath = null;

    public function __construct(
        int $volumeId,
        private readonly Assets $assets,
        private readonly VolumeFolder $parentFolder,
    ) {
        parent::__construct($volumeId);
    }

    protected function assetsService(): Assets
    {
        return $this->assets;
    }

    protected function firstOrCreateFolder(?string $folderPath): VolumeFolder
    {
        $this->createdFolderPath = $folderPath;

        return $this->parentFolder;
    }
}

function folderActionFolder(int $id, string $path, ?int $parentId = null): VolumeFolder
{
    return new VolumeFolder([
        'id' => $id,
        'volumeId' => 7,
        'parentId' => $parentId,
        'name' => $path === '' ? 'Volume' : basename(rtrim($path, '/')),
        'path' => $path,
    ]);
}

it('returns the Craft root folder without recursion', function() {
    $volume = $this->createMock(Volume::class);
    $root = folderActionFolder(1, '');
    $volumes = $this->createMock(Volumes::class);
    $volumes->expects($this->once())->method('getVolumeById')->with(7)->willReturn($volume);
    $assets = $this->createMock(Assets::class);
    $assets->expects($this->once())
        ->method('ensureFolderByFullPathAndVolume')
        ->with('', $volume, true)
        ->willReturn($root);

    expect((new FolderCreateTestAction(7, $assets, $volumes))->firstOrCreate(null))->toBe($root);
});

it('delegates nested folder creation to Craft without filesystem writes', function() {
    $volume = $this->createMock(Volume::class);
    $folder = folderActionFolder(3, 'photos/holidays/', 2);
    $volumes = $this->createMock(Volumes::class);
    $volumes->method('getVolumeById')->with(7)->willReturn($volume);
    $assets = $this->createMock(Assets::class);
    $assets->expects($this->once())
        ->method('ensureFolderByFullPathAndVolume')
        ->with('photos/holidays/', $volume, true)
        ->willReturn($folder);

    expect((new FolderCreateTestAction(7, $assets, $volumes))->firstOrCreate('/photos/holidays/'))->toBe($folder);
});

it('fails explicitly when creating a folder for an unknown volume', function() {
    $volumes = $this->createMock(Volumes::class);
    $volumes->method('getVolumeById')->with(7)->willReturn(null);
    $assets = $this->createMock(Assets::class);

    expect(fn() => (new FolderCreateTestAction(7, $assets, $volumes))->firstOrCreate('photos'))
        ->toThrow(InvalidArgumentException::class, 'Volume 7 not found');
});

it('never deletes the volume root folder', function() {
    $assets = $this->createMock(Assets::class);
    $assets->expects($this->never())->method('findFolder');
    $assets->expects($this->never())->method('deleteFoldersByIds');

    (new FolderDeleteTestAction(7, $assets))->delete('/');
});

it('deletes folder elements safely without deleting the remote directory', function() {
    $folder = folderActionFolder(4, 'photos/');
    $assets = $this->createMock(Assets::class);
    $assets->expects($this->once())
        ->method('findFolder')
        ->with(['volumeId' => 7, 'path' => 'photos/'])
        ->willReturn($folder);
    $assets->expects($this->once())->method('deleteFoldersByIds')->with(4, false);

    (new FolderDeleteTestAction(7, $assets))->delete('photos');
});

it('safely removes a conflicting target and rewrites descendant paths', function() {
    $source = folderActionFolder(10, 'source/', 1);
    $target = folderActionFolder(20, 'archive/source/', 30);
    $descendant = folderActionFolder(11, 'source/child/', 10);
    $parent = folderActionFolder(30, 'archive/', 1);
    $storedPaths = [];

    $assets = $this->createMock(Assets::class);
    $assets->expects($this->exactly(2))
        ->method('findFolder')
        ->willReturnCallback(fn(array $criteria) => $criteria['path'] === 'archive/source/' ? $target : $source);
    $assets->expects($this->once())->method('deleteFoldersByIds')->with(20, false);
    $assets->expects($this->once())
        ->method('getAllDescendantFolders')
        ->with($source, 'path', false)
        ->willReturn([11 => $descendant]);
    $assets->expects($this->exactly(2))
        ->method('storeFolderRecord')
        ->willReturnCallback(function(VolumeFolder $folder) use (&$storedPaths): void {
            $storedPaths[] = $folder->path;
        });

    $action = new FolderRenameTestAction(7, $assets, $parent);
    $result = $action->rename('source', 'archive/source');

    expect($result)->toBe($source)
        ->and($source->path)->toBe('archive/source/')
        ->and($source->name)->toBe('source')
        ->and($source->parentId)->toBe(30)
        ->and($descendant->path)->toBe('archive/source/child/')
        ->and($action->createdFolderPath)->toBe('archive')
        ->and($storedPaths)->toBe(['archive/source/', 'archive/source/child/']);
});

it('refuses to move or rename the volume root folder', function() {
    $assets = $this->createMock(Assets::class);
    $parent = folderActionFolder(1, '');
    $action = new FolderRenameTestAction(7, $assets, $parent);

    expect(fn() => $action->rename('', 'renamed'))
        ->toThrow(InvalidArgumentException::class, 'The volume root folder cannot be moved or renamed');
});
