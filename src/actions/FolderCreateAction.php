<?php

namespace Noo\CraftCloudinary\actions;

use craft\models\VolumeFolder;

class FolderCreateAction extends BaseCloudinaryAction
{
    /**
     * Webhook entry point receiving an absolute Cloudinary folder path.
     */
    public function createFromWebhook(?string $folderPath): ?VolumeFolder
    {
        $relativePath = $this->relativeAssetFolder($folderPath);

        if ($relativePath === null) {
            $this->logSkippedOutsideSubpath('folder creation', (string) $folderPath);
            return null;
        }

        return $this->firstOrCreate($relativePath);
    }

    /**
     * @param ?string $folderPath relative to the volume subpath, eg. 'parent/new-folder'
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
