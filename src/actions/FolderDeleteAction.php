<?php

namespace Noo\CraftCloudinary\actions;

class FolderDeleteAction extends BaseCloudinaryAction
{
    /**
     * @param ?string $folderPath absolute Cloudinary path, eg. 'parent/folder-to-delete'
     */
    public function delete(?string $folderPath): void
    {
        $relativePath = $this->relativeAssetFolder($folderPath);

        if ($relativePath === null) {
            $this->logSkippedOutsideSubpath('folder deletion', (string) $folderPath);
            return;
        }

        $folderPath = $this->formatPath($relativePath);

        // Don't delete the base folder
        if ($folderPath === '') {
            return;
        }

        $assets = $this->assetsService();
        $folder = $assets->findFolder([
            'volumeId' => $this->volumeId,
            'path' => $folderPath,
        ]);

        if ($folder !== null && $folder->id !== null) {
            $assets->deleteFoldersByIds($folder->id, false);
        }
    }
}
