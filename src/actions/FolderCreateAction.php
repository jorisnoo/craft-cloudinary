<?php

namespace jorisnoo\craftcloudinary\actions;

use craft\records\VolumeFolder;

class FolderCreateAction extends BaseCloudinaryAction
{
    /**
     * @param ?string $folderPath, eg. 'parent/new-folder'
     */
    public function firstOrCreate(?string $folderPath): VolumeFolder
    {
        $folderPath = $this->formatPath($folderPath);

        // First, check if the folder already exists
        $existingFolder = VolumeFolder::findOne([
            'volumeId' => $this->volumeId,
            'path' => $folderPath,
        ]);

        if ($existingFolder) {
            return $existingFolder;
        }

        // If it doesn't, get or create the parent folder
        $parentFolder = $this->firstOrCreate(dirname($folderPath));

        // Folder name is the basename of the path
        $folderName = basename($folderPath);

        // Store folder
        $record = new VolumeFolder([
            'parentId' => $parentFolder->id,
            'volumeId' => $this->volumeId,
            'name' => $folderName,
            'path' => $this->formatPath($folderPath),
        ]);

        $record->save();

        return $record;
    }
}
