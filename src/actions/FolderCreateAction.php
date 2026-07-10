<?php

namespace Noo\CraftCloudinary\actions;

use craft\models\VolumeFolder;

class FolderCreateAction extends BaseCloudinaryAction
{
    /**
     * @param ?string $folderPath, eg. 'parent/new-folder'
     */
    public function firstOrCreate(?string $folderPath): VolumeFolder
    {
        $folderPath = $this->formatPath($folderPath);
        $volume = $this->volumesService()->getVolumeById($this->volumeId);

        if ($volume === null) {
            throw new \InvalidArgumentException("Volume {$this->volumeId} not found");
        }

        return $this->assetsService()->ensureFolderByFullPathAndVolume(
            $folderPath,
            $volume,
            justRecord: true,
        );
    }
}
