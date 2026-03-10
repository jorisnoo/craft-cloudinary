<?php

namespace Noo\CraftCloudinary\actions;

use craft\records\VolumeFolder;
use Noo\CraftCloudinary\Cloudinary;

class FolderCreateAction extends BaseCloudinaryAction
{
    /**
     * @param ?string $folderPath, eg. 'parent/new-folder'
     */
    public function firstOrCreate(?string $folderPath): VolumeFolder
    {
        $folderPath = $this->formatPath($folderPath);
        Cloudinary::log("FolderCreateAction - Looking for folder: {$folderPath} (Volume: {$this->volumeId})");

        // First, check if the folder already exists
        $existingFolder = VolumeFolder::findOne([
            'volumeId' => $this->volumeId,
            'path' => $folderPath,
        ]);

        if ($existingFolder) {
            Cloudinary::log("Folder already exists - Folder ID: {$existingFolder->id}, Path: {$existingFolder->path}");
            return $existingFolder;
        }

        Cloudinary::log("Folder does not exist, creating new folder");

        // If it doesn't, get or create the parent folder
        $parentFolderPath = dirname($folderPath);
        Cloudinary::log("Getting or creating parent folder: {$parentFolderPath}");
        $parentFolder = $this->firstOrCreate($parentFolderPath);
        Cloudinary::log("Parent folder retrieved - Folder ID: {$parentFolder->id}");

        // Folder name is the basename of the path
        $folderName = basename($folderPath);
        Cloudinary::log("Creating folder with name: {$folderName}, Parent ID: {$parentFolder->id}");

        // Store folder
        $record = new VolumeFolder([
            'parentId' => $parentFolder->id,
            'volumeId' => $this->volumeId,
            'name' => $folderName,
            'path' => $this->formatPath($folderPath),
        ]);

        $saved = $record->save();

        if ($saved) {
            Cloudinary::log("Folder created successfully - Folder ID: {$record->id}, Path: {$record->path}");
        } else {
            Cloudinary::log("Folder creation failed - Errors: " . json_encode($record->getErrors()), 'error');
        }

        return $record;
    }
}
