<?php

namespace jorisnoo\craftcloudinary\actions;

use craft\records\VolumeFolder;

class FolderRenameAction extends BaseCloudinaryAction
{
    /**
     * @param ?string $fromPath
     * @param ?string $toPath
     */
    public function rename(?string $fromPath, ?string $toPath): void
    {
        $fromPath = $this->formatPath($fromPath);
        $toPath = $this->formatPath($toPath);

        $folder = VolumeFolder::findOne([
            'volumeId' => $this->volumeId,
            'path' => $fromPath,
        ]);

        // If the folder exists, move it
        if ($folder) {
            // Get the parent folder by passing the dirname of the new folder destination
            $newParentFolder = (new FolderCreateAction($this->volumeId))
                ->firstOrCreate(dirname($toPath));

            $folder->path = $toPath;
            $folder->name = basename($toPath);
            $folder->parentId = $newParentFolder->id;
            $folder->save();

            return;
        }

        // If it doesn't, create it
        (new FolderCreateAction($this->volumeId))->firstOrCreate($toPath);
    }
}
