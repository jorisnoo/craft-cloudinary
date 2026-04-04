<?php

namespace Noo\CraftCloudinary\fs;

use Cloudinary\Cloudinary;
use Craft;
use craft\flysystem\base\FlysystemFs;
use craft\helpers\App;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use Noo\CraftCloudinary\Cloudinary as CloudinaryPlugin;
use ThomasVantuycom\FlysystemCloudinary\CloudinaryAdapter;

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

    public function write(string $path, string $contents, array $config = []): void
    {
        $publicId = pathinfo(basename($path), PATHINFO_FILENAME);
        CloudinaryPlugin::getInstance()->syncGuard->markUploaded($publicId);

        parent::write($path, $contents, $config);
    }

    public function writeFileFromStream(string $path, $stream, array $config = []): void
    {
        $publicId = pathinfo(basename($path), PATHINFO_FILENAME);
        CloudinaryPlugin::getInstance()->syncGuard->markUploaded($publicId);

        parent::writeFileFromStream($path, $stream, $config);
    }

    public function getCloudinaryFilesystem(): Filesystem
    {
        return $this->filesystem();
    }

    protected function createAdapter(): FilesystemAdapter
    {
        $client = $this->getClient();

        return new CloudinaryAdapter(
            client: $client,
        );
    }

    protected function invalidateCdnPath(string $path): bool
    {
        return true;
    }
}
