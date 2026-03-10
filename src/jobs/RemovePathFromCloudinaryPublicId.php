<?php

namespace Noo\CraftCloudinary\jobs;

use Cloudinary\Api\Exception\NotFound;
use Cloudinary\Cloudinary;
use Craft;
use craft\queue\BaseJob;
use Noo\CraftCloudinary\Cloudinary as CloudinaryPlugin;

class RemovePathFromCloudinaryPublicId extends BaseJob
{
    public function __construct(
        public int $volumeId,
        public string $publicId,
        public string $resourceType,
    ) {
        parent::__construct();
    }

    public function getDescription(): string
    {
        return 'Remove path from a cloudinary public ids';
    }

    /**
     * Synchronising the assets using the cli command
     */
    public function execute($queue): void
    {
        $volume = Craft::$app->getVolumes()->getVolumeById($this->volumeId);
        $newPublicId = basename($this->publicId);

        if ($newPublicId === $this->publicId) {
            return;
        }

        if (!$volume) {
            throw new NotFound('Volume not found');
        }

        /* @var Cloudinary $client */
        $client = $volume->getFs()->getClient();

        CloudinaryPlugin::log("Renaming public_id from '{$this->publicId}' to '$newPublicId'");

        try {
            $client->uploadApi()->rename($this->publicId, $newPublicId, [
                "resource_type" => $this->resourceType,
                'invalidate' => true,
            ]);
        } catch (NotFound $e) {
            CloudinaryPlugin::log("Renaming failed. Asset with public_id '{$this->publicId}' not found.");
        }
    }
}
