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

        $existingFolder = VolumeFolder::findOne([
            'volumeId' => $this->volumeId,
            'path' => $folderPath,
        ]);

        if ($existingFolder) {
            return $existingFolder;
        }

        $parentFolder = $this->firstOrCreate(dirname($folderPath));

        $record = new VolumeFolder([
            'parentId' => $parentFolder->id,
            'volumeId' => $this->volumeId,
            'name' => basename($folderPath),
            'path' => $this->formatPath($folderPath),
        ]);

        $saved = $record->save();

        if (!$saved) {
            Cloudinary::log("Folder creation failed - Path: {$folderPath}, Errors: " . json_encode($record->getErrors()), 'error');
        }

        return $record;
    }
}
