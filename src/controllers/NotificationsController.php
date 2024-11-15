<?php

namespace jorisnoo\craftcloudinary\controllers;

use Craft;
use craft\helpers\App;
use craft\web\Controller;
use jorisnoo\craftcloudinary\actions\FolderDeleteAction;
use jorisnoo\craftcloudinary\actions\AssetUploadAction;
use jorisnoo\craftcloudinary\actions\FolderRenameAction;
use jorisnoo\craftcloudinary\Cloudinary;
use jorisnoo\craftcloudinary\fs\CloudinaryFs;
use jorisnoo\craftcloudinary\actions\FolderCreateAction;
use jorisnoo\craftcloudinary\actions\AssetChangeDisplayNameAction;
use jorisnoo\craftcloudinary\actions\AssetDeleteAction;
use jorisnoo\craftcloudinary\actions\AssetMoveAction;
use jorisnoo\craftcloudinary\actions\AssetRenameAction;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class NotificationsController extends Controller
{
    public $enableCsrfValidation = false;

    protected array|bool|int $allowAnonymous = true;

    // /actions/_cloudinary/notifications/process?volume=1
    public function actionProcess(): Response
    {
        if (!$this->request->getIsPost()) {
            return $this->asSuccess();
        }

        $volumeId = $this->request->getRequiredQueryParam('volume');
        $notificationType = $this->request->getRequiredBodyParam('notification_type');

        $fs = $this->verifyVolume($volumeId);
        $this->verifyCloudinarySignature($fs);

        Cloudinary::log("Webhook received for type $notificationType");

        match ($notificationType) {

            // https://cloudinary.com/documentation/notifications#rename
            'rename' => (new AssetRenameAction($volumeId))->rename(
                fromPublicId: $this->request->getRequiredBodyParam('from_public_id'),
                toPublicId: $this->request->getRequiredBodyParam('to_public_id'),
                assetFolder: $this->request->getRequiredBodyParam('asset_folder'),
                resourceType: $this->request->getRequiredBodyParam('resource_type'),
            ),

            // https://cloudinary.com/documentation/notifications#change_display_name
            'resource_display_name_changed' => (new AssetChangeDisplayNameAction($volumeId))->change(
                resources: $this->request->getRequiredBodyParam('resources'),
            ),

            // https://cloudinary.com/documentation/notifications#create_asset_folder
            'create_folder' => (new FolderCreateAction($volumeId))->firstOrCreate(
                folderPath: $this->request->getRequiredBodyParam('folder_path'),
            ),

            // https://cloudinary.com/documentation/notifications#move_between_asset_folders
            'move' => (new AssetMoveAction($volumeId))->move(
                resources: $this->request->getRequiredBodyParam('resources'),
            ),

            // https://cloudinary.com/documentation/notifications#move_an_asset_folder
            'move_or_rename_asset_folder' => (new FolderRenameAction($volumeId))->rename(
                fromPath: $this->request->getRequiredBodyParam('from_path'),
                toPath: $this->request->getRequiredBodyParam('to_path'),
            ),

            // https://cloudinary.com/documentation/notifications#delete_an_asset
            'delete' => (new AssetDeleteAction($volumeId))->delete(
                resources: $this->request->getRequiredBodyParam('resources'),
            ),

            // https://cloudinary.com/documentation/notifications#delete_an_asset_folder
            'delete_folder' => (new FolderDeleteAction($volumeId))->delete(
                folderPath: $this->request->getRequiredBodyParam('folder_path'),
            ),

            // https://cloudinary.com/documentation/notifications#upload_simple
            'upload' => (new AssetUploadAction($volumeId))->upload(
                publicId: $this->request->getRequiredBodyParam('public_id'),
                assetFolder: $this->request->getRequiredBodyParam('asset_folder'),
                resourceType: $this->request->getRequiredBodyParam('resource_type'),
                displayName: $this->request->getRequiredBodyParam('display_name'),
                size: $this->request->getRequiredBodyParam('bytes'),
                width: $this->request->getBodyParam('width'),
                height: $this->request->getBodyParam('height'),
                format: $this->request->getBodyParam('format'),
            ),

            default => null,
        };

        return $this->asSuccess();
    }

    protected function verifyVolume($volumeId): CloudinaryFs
    {
        $volume = Craft::$app->getVolumes()->getVolumeById($volumeId);

        if ($volume === null) {
            throw new NotFoundHttpException('Volume not found');
        }

        $fs = $volume->getFs();

        if (!$fs instanceof CloudinaryFs) {
            throw new BadRequestHttpException('Invalid volume');
        }

        return $fs;
    }

    protected function verifyCloudinarySignature(CloudinaryFs $fs): void
    {
        // Verify signature
        $apiSecret = App::parseEnv($fs->apiSecret);
        $body = $this->request->getRawBody();
        $timestamp = $this->request->getHeaders()->get('X-Cld-Timestamp');
        $signature = $this->request->getHeaders()->get('X-Cld-Signature');
        $signedPayload = $body . $timestamp;

        if (sha1($signedPayload . $apiSecret) !== $signature) {
            throw new BadRequestHttpException('Invalid signature');
        }
    }
}
