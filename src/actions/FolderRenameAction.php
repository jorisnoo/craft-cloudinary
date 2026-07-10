<?php

namespace Noo\CraftCloudinary\actions;

use craft\models\VolumeFolder;

class FolderRenameAction extends BaseCloudinaryAction
{
    /**
     * @param ?string $fromPath
     * @param ?string $toPath
     */
    public function rename(?string $fromPath, ?string $toPath): VolumeFolder
    {
        $fromPath = $this->formatPath($fromPath);
        $toPath = $this->formatPath($toPath);

        if ($fromPath === '' || $toPath === '') {
            throw new \InvalidArgumentException('The volume root folder cannot be moved or renamed');
        }

        $assets = $this->assetsService();
        $existingTargetFolder = $assets->findFolder([
            'volumeId' => $this->volumeId,
            'path' => $toPath,
        ]);

        $oldFolder = $assets->findFolder([
            'volumeId' => $this->volumeId,
            'path' => $fromPath,
        ]);

        if ($existingTargetFolder && $oldFolder) {
            // If both a "from" and a "to" path already exists,
            // delete the target and rename the "from" to the new destination (below)
            $assets->deleteFoldersByIds($existingTargetFolder->id, false);
        } elseif ($existingTargetFolder) {
            // If only the target already exists but no "from" folder, don't do anything
            return $existingTargetFolder;
        }

        // If the "from" folder exists, but no target folder, move the existing one
        if ($oldFolder) {
            $descendantFolders = $assets->getAllDescendantFolders($oldFolder, withParent: false);

            // Get the parent folder by passing the dirname of the new folder destination
            $newParentFolder = $this->firstOrCreateFolder(dirname($toPath));

            $oldFolder->path = $toPath;
            $oldFolder->name = basename(rtrim($toPath, '/'));
            $oldFolder->parentId = $newParentFolder->id;
            $assets->storeFolderRecord($oldFolder);

            foreach ($descendantFolders as $descendantFolder) {
                if (!str_starts_with((string) $descendantFolder->path, $fromPath)) {
                    continue;
                }

                $descendantFolder->path = $toPath . substr($descendantFolder->path, strlen($fromPath));
                $assets->storeFolderRecord($descendantFolder);
            }

            return $oldFolder;
        }

        // If it doesn't, create it
        return $this->firstOrCreateFolder($toPath);
    }

    protected function firstOrCreateFolder(?string $folderPath): VolumeFolder
    {
        return (new FolderCreateAction($this->volumeId))->firstOrCreate($folderPath);
    }
}
