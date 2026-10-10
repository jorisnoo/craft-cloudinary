<?php

namespace Noo\CraftCloudinary\actions;

use Craft;
use craft\elements\Asset;
use craft\models\VolumeFolder;
use Noo\CraftCloudinary\Cloudinary;

class FolderRenameAction extends BaseCloudinaryAction
{
    /**
     * @param ?string $fromPath absolute Cloudinary path
     * @param ?string $toPath absolute Cloudinary path
     */
    public function rename(?string $fromPath, ?string $toPath): ?VolumeFolder
    {
        if ($this->folderWasChangedFromCraft($fromPath) || $this->folderWasChangedFromCraft($toPath)) {
            $this->logSkippedCraftChange("folder rename of '{$fromPath}'");
            return null;
        }

        $relativeFromPath = $this->relativeAssetFolder($fromPath);
        $relativeToPath = $this->relativeAssetFolder($toPath);

        if ($relativeFromPath === null && $relativeToPath === null) {
            $this->logSkippedOutsideSubpath('folder rename', (string) $fromPath);
            return null;
        }

        if ($relativeToPath === null) {
            // The folder was moved out of the volume subpath, so it no
            // longer belongs to this volume.
            $this->deleteFolder($fromPath);
            return null;
        }

        if ($relativeFromPath === null) {
            // The folder was moved into the volume subpath. Its assets can't
            // be imported from this webhook, so leave them to a sync.
            Cloudinary::log(
                "Folder '{$toPath}' was moved into the volume subpath from '{$fromPath}' - run a sync to import its assets",
                'warning',
            );

            return $this->firstOrCreateFolder($relativeToPath);
        }

        $fromPath = $this->formatPath($relativeFromPath);
        $toPath = $this->formatPath($relativeToPath);

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
            // Both exist: merge the "from" folder into the target. The
            // target's assets still exist in Cloudinary, so deleting its
            // records would lose them along with their relations.
            $this->mergeFolder($oldFolder, $fromPath, $toPath);

            return $existingTargetFolder;
        }

        if ($existingTargetFolder) {
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

    /**
     * Moves the assets of a folder and its subfolders into the matching
     * folders below $toPath, then removes the emptied folder records.
     */
    protected function mergeFolder(VolumeFolder $oldFolder, string $fromPath, string $toPath): void
    {
        $assets = $this->assetsService();
        $folders = [$oldFolder, ...$assets->getAllDescendantFolders($oldFolder, withParent: false)];

        foreach ($folders as $folder) {
            $targetPath = $toPath . substr((string) $folder->path, strlen($fromPath));
            $targetFolder = $this->firstOrCreateFolder($targetPath);

            foreach ($this->assetsInFolder((int) $folder->id) as $asset) {
                $this->moveAssetRecord($asset, $targetFolder);
            }
        }

        // Only empty folder records remain, so no assets are deleted here.
        $assets->deleteFoldersByIds($oldFolder->id, false);
    }

    /**
     * @return Asset[]
     */
    protected function assetsInFolder(int $folderId): array
    {
        return Asset::find()->folderId($folderId)->status(null)->all();
    }

    /**
     * Setting folderId directly only updates the record; Craft won't try to
     * move the file, which is already in place.
     */
    protected function moveAssetRecord(Asset $asset, VolumeFolder $folder): void
    {
        $asset->folderId = $folder->id;
        Craft::$app->getElements()->saveElement($asset);
    }

    protected function firstOrCreateFolder(?string $folderPath): VolumeFolder
    {
        return (new FolderCreateAction($this->volumeId))->firstOrCreate($folderPath);
    }

    protected function deleteFolder(?string $folderPath): void
    {
        (new FolderDeleteAction($this->volumeId))->delete($folderPath);
    }
}
