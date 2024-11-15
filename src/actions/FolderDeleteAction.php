<?php

namespace jorisnoo\craftcloudinary\actions;

use craft\records\VolumeFolder;

class FolderDeleteAction extends BaseCloudinaryAction
{
    /**
     * @param ?string $folderPath, eg. 'parent/folder-to-delete'
     */
    public function delete(?string $folderPath): void
    {
        $folderPath = $this->formatPath($folderPath);

        // Don't delete th base folder
        if ($folderPath === null) {
            return;
        }

        VolumeFolder::deleteAll([
            'volumeId' => $this->volumeId,
            'path' => $folderPath,
        ]);
    }
}
