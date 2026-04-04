<?php

namespace Noo\CraftCloudinary\services;

use Cloudinary\Cloudinary;
use Craft;
use Noo\CraftCloudinary\fs\CloudinaryFs;
use yii\base\Component;
use yii\base\InvalidArgumentException;

class CloudinaryApi extends Component
{
    /** @var Cloudinary[] */
    private array $clients = [];

    public function getClientForVolume(int $volumeId): Cloudinary
    {
        if (isset($this->clients[$volumeId])) {
            return $this->clients[$volumeId];
        }

        $volume = Craft::$app->getVolumes()->getVolumeById($volumeId);

        if ($volume === null) {
            throw new InvalidArgumentException("Volume {$volumeId} not found");
        }

        $fs = $volume->getFs();

        if (!$fs instanceof CloudinaryFs) {
            $fs = $volume->getTransformFs();
        }

        if (!$fs instanceof CloudinaryFs) {
            throw new InvalidArgumentException("Volume {$volumeId} does not use a Cloudinary filesystem");
        }

        return $this->clients[$volumeId] = $fs->getClient();
    }

    public function getClientForFs(CloudinaryFs $fs): Cloudinary
    {
        return $fs->getClient();
    }
}
