<?php

namespace Noo\CraftCloudinary\jobs;

use Craft;
use craft\queue\BaseJob;
use Noo\CraftCloudinary\Cloudinary;

class SyncVolume extends BaseJob
{
    public function __construct(
        public int $volumeId,
        public bool $force = false,
    ) {
        parent::__construct();
    }

    public function execute($queue): void
    {
        Cloudinary::getInstance()->syncReconciler->reconcile($this->volumeId, false, $this->force);
    }

    public function getDescription(): string
    {
        $volume = Craft::$app->getVolumes()->getVolumeById($this->volumeId);
        $name = $volume?->name ?? "#{$this->volumeId}";
        $suffix = $this->force ? ' [forced]' : '';

        return "Syncing Cloudinary volume \"{$name}\"{$suffix}";
    }
}
