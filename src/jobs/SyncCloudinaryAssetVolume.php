<?php

namespace jorisnoo\craftcloudinary\jobs;

use Craft;
use craft\queue\BaseJob;

class SyncCloudinaryAssetVolume extends BaseJob
{
    public function __construct(
        public string $volumeHandle,
    )
    {
        parent::__construct();
    }

    public function getDescription(): string
    {
        return 'Synchronising asset volumes with Cloudinary';
    }

    /**
     * Synchronising the assets using the cli command
     */
    public function execute($queue): void
    {
        Craft::$app->controllerNamespace = 'craft\console\controllers';

        // Stop all running cli indexing sessions
        Craft::$app->runAction('index-assets/cleanup');

        Craft::$app->runAction('index-assets/one', [
            $this->volumeHandle,
            '--delete-missing-assets' => '1',
            '--interactive' => '0',
        ]);
    }
}
