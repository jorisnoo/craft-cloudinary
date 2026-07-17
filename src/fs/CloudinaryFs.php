<?php

namespace Noo\CraftCloudinary\fs;

use Cloudinary\Cloudinary;
use Craft;
use craft\flysystem\base\FlysystemFs;
use craft\helpers\App;
use Generator;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use Noo\CraftCloudinary\Cloudinary as CloudinaryPlugin;
use Noo\CraftCloudinary\helpers\CloudinaryAssetSearch;
use Noo\CraftCloudinary\helpers\FsListings;

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

        yield from FsListings::fromResources($resources, $directory, $recursive);
    }

    /**
     * renameDirectory() deletes the old directories after moving their files
     * out, so it must see a strongly consistent listing - a Search-lagged one
     * could miss a just-uploaded file, leaving it to be destroyed in the
     * cleanup pass.
     */
    public function renameDirectory(string $path, string $newName): void
    {
        $this->listViaAdapter = true;

        try {
            parent::renameDirectory($path, $newName);
        } finally {
            $this->listViaAdapter = false;
        }
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
            basename($path),
        );
    }

    public function getCloudinaryFilesystem(): Filesystem
    {
        return $this->filesystem();
    }

    protected function createAdapter(): FilesystemAdapter
    {
        return new LocalUrlAdapter($this->getClient());
    }

    protected function invalidateCdnPath(string $path): bool
    {
        return true;
    }
}
