<?php

namespace jorisnoo\craftcloudinary\fs;

use Cloudinary\Cloudinary;
use Craft;
use craft\flysystem\base\FlysystemFs;
use craft\helpers\App;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use ThomasVantuycom\FlysystemCloudinary\CloudinaryAdapter;

class CloudinaryFs extends FlysystemFs
{
    public string $cloudName = '';

    public string $apiKey = '';

    public string $apiSecret = '';

    public static function displayName(): string
    {
        return 'Cloudinary';
    }

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['cloudName', 'apiKey', 'apiSecret'], 'required'],
        ]);
    }

    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('_cloudinary/fsSettings', [
            'fs' => $this,
        ]);
    }

    public function getShowHasUrlSetting(): bool
    {
        return false;
    }

    public function getClient(): Cloudinary
    {
        $config = [
            'cloud' => [
                'cloud_name' => App::parseEnv($this->cloudName),
                'api_key' => App::parseEnv($this->apiKey),
                'api_secret' => App::parseEnv($this->apiSecret),
            ],
            'url' => [
                'analytics' => false,
                'forceVersion' => false,
            ],
        ];

        return new Cloudinary($config);
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
            prefix: '',
            mimeTypeDetector: null,
            dynamicFolders: true,
        );
    }

    protected function invalidateCdnPath(string $path): bool
    {
        return true;
    }
}
