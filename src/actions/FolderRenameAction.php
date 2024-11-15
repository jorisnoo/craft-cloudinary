<?php

namespace jorisnoo\craftcloudinary\actions;

use craft\records\VolumeFolder;

class FolderRenameAction extends BaseCloudinaryAction
{
    /**
     * @param ?string $fromPath
     * @param ?string $toPath
     */
    public function rename(?string $fromPath, ?string $toPath)
    {
        $fromPath = $this->formatPath($fromPath);
        $toPath = $this->formatPath($toPath);

        $existingTargetFolder = VolumeFolder::findOne([
            'volumeId' => $this->volumeId,
            'path' => $toPath,
        ]);

        $oldFolder = VolumeFolder::findOne([
            'volumeId' => $this->volumeId,
            'path' => $fromPath,
        ]);

        if ($existingTargetFolder && $oldFolder) {
            // If both a "from" and a "to" path already exists,
            // delete the target and rename the "from" to the new destination (below)
            $existingTargetFolder->delete();
        } elseif ($existingTargetFolder && !$oldFolder) {
            // If only the target already exists but no "from" folder, don't do anything
            return $existingTargetFolder;
        }

        // If the "from" folder exists, but no target folder, move the existing one
        if ($oldFolder) {
            // Get the parent folder by passing the dirname of the new folder destination
            $newParentFolder = (new FolderCreateAction($this->volumeId))
                ->firstOrCreate(dirname($toPath));

            $oldFolder->path = $toPath;
            $oldFolder->name = basename($toPath);
            $oldFolder->parentId = $newParentFolder->id;
            $oldFolder->save();

            return $oldFolder;
        }

        // If it doesn't, create it
        return (new FolderCreateAction($this->volumeId))->firstOrCreate($toPath);
    }
}
