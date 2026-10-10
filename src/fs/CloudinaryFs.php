<?php

namespace Noo\CraftCloudinary\fs;

use Cloudinary\Cloudinary;
use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\flysystem\base\FlysystemFs;
use craft\helpers\App;
use Generator;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use Noo\CraftCloudinary\Cloudinary as CloudinaryPlugin;
use Noo\CraftCloudinary\helpers\CloudinaryAssetSearch;
use Noo\CraftCloudinary\helpers\FsListings;
use Noo\CraftCloudinary\helpers\PublicIds;
use Noo\CraftCloudinary\services\SyncGuard;

class CloudinaryFs extends FlysystemFs
{
    public bool $hasUrls = true;

    public string $cloudName = '';

    public string $apiKey = '';

    public string $apiSecret = '';

    public static function displayName(): string
    {
        return 'Cloudinary';
    }

    public function getShowHasUrlSetting(): bool
    {
        return false;
    }

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['cloudName', 'apiKey', 'apiSecret'], 'required'],
        ]);
    }

    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('cloudinary/fsSettings', [
            'fs' => $this,
        ]);
    }

    private ?Cloudinary $_client = null;

    public function getClient(): Cloudinary
    {
        return $this->_client ??= new Cloudinary([
            'cloud' => [
                'cloud_name' => App::parseEnv($this->cloudName),
                'api_key' => App::parseEnv($this->apiKey),
                'api_secret' => App::parseEnv($this->apiSecret),
            ],
            'url' => [
                'analytics' => false,
                'forceVersion' => false,
            ],
        ]);
    }

    private bool $listViaAdapter = false;

    /**
     * Craft's asset indexer enumerates the volume through this method. The
     * adapter's listContents() costs roughly two Admin API calls per folder;
     * the Search API returns the same listing in one request per 500 assets.
     * The Search index can lag a few seconds behind writes, which is
     * acceptable for indexing but not for renameDirectory().
     */
    public function getFileList(string $directory = '', bool $recursive = true): Generator
    {
        if ($this->listViaAdapter) {
            yield from parent::getFileList($directory, $recursive);
            return;
        }

        $directory = trim($directory, '/');

        $resources = CloudinaryAssetSearch::resources($this->getClient(), $directory, [
            'public_id',
            'asset_folder',
            'resource_type',
            'format',
            'bytes',
            'created_at',
        ]);

        yield from FsListings::fromResources($resources, $directory, $recursive, $this->knownFilenames());
    }

    /**
     * Filenames of the Craft assets stored in this filesystem, keyed by
     * FsListings::knownFilenameKey().
     *
     * @return array<string, string>
     */
    private function knownFilenames(): array
    {
        $knownFilenames = [];

        foreach (Craft::$app->getVolumes()->getAllVolumes() as $volume) {
            if ($volume->getFs() !== $this && $volume->getFsHandle() !== $this->handle) {
                continue;
            }

            $subpath = trim($volume->getSubpath(false), '/');

            $rows = (new Query())
                ->select(['assets.filename', 'folders.path'])
                ->from(['assets' => Table::ASSETS])
                ->innerJoin(['elements' => Table::ELEMENTS], '[[elements.id]] = [[assets.id]]')
                ->innerJoin(['folders' => Table::VOLUMEFOLDERS], '[[folders.id]] = [[assets.folderId]]')
                ->where([
                    'assets.volumeId' => $volume->id,
                    'elements.dateDeleted' => null,
                ])
                ->all();

            foreach ($rows as $row) {
                $folder = trim($subpath . '/' . trim((string) $row['path'], '/'), '/');
                $key = FsListings::knownFilenameKey($folder, PublicIds::keyFromPath($row['filename']));
                $knownFilenames[$key] = $row['filename'];
            }
        }

        return $knownFilenames;
    }

    /**
     * renameDirectory() deletes the old directories after moving their files
     * out, so it must see a strongly consistent listing - a Search-lagged one
     * could miss a just-uploaded file, leaving it to be destroyed in the
     * cleanup pass.
     */
    public function renameDirectory(string $path, string $newName): void
    {
        $parent = dirname($path);
        $newPath = $parent === '.' ? $newName : "{$parent}/{$newName}";

        $this->markChanged(SyncGuard::folderKey($path));
        $this->markChanged(SyncGuard::folderKey($newPath));

        $this->listViaAdapter = true;

        try {
            parent::renameDirectory($path, $newName);
        } finally {
            $this->listViaAdapter = false;
        }
    }

    public function renameFile(string $path, string $newPath, $config = []): void
    {
        $this->markChanged(PublicIds::keyFromPath($path));
        $this->markChanged(PublicIds::keyFromPath($newPath));

        parent::renameFile($path, $newPath, $config);
    }

    public function copyFile(string $path, string $newPath, $config = []): void
    {
        $this->markUploaded($newPath);

        parent::copyFile($path, $newPath, $config);
    }

    public function createDirectory(string $path, array $config = []): void
    {
        $this->markChanged(SyncGuard::folderKey($path));

        parent::createDirectory($path, $config);
    }

    public function deleteDirectory(string $path): void
    {
        $this->markChanged(SyncGuard::folderKey($path));

        parent::deleteDirectory($path);
    }

    public function write(string $path, string $contents, array $config = []): void
    {
        $this->markUploaded($path);

        parent::write($path, $contents, $config);
    }

    public function writeFileFromStream(string $path, $stream, array $config = []): void
    {
        $this->markUploaded($path);

        parent::writeFileFromStream($path, $stream, $config);
    }

    private function markUploaded(string $path): void
    {
        CloudinaryPlugin::getInstance()->syncGuard->markUploaded(
            App::parseEnv($this->cloudName),
            PublicIds::keyFromPath($path),
        );
    }

    private function markChanged(string $subject): void
    {
        CloudinaryPlugin::getInstance()->syncGuard->markChangedFromCraft(
            App::parseEnv($this->cloudName),
            $subject,
        );
    }

    public function getCloudinaryFilesystem(): Filesystem
    {
        return $this->filesystem();
    }

    protected function createAdapter(): FilesystemAdapter
    {
        return new CloudinaryAdapter($this->getClient());
    }

    protected function invalidateCdnPath(string $path): bool
    {
        return true;
    }
}
