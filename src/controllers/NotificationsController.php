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
        Cloudinary::log("=== Webhook Request Received ===");

        $referer = $this->request->getReferrer() ?? 'none';
        Cloudinary::log("HTTP_REFERER: {$referer}");

        if (!$this->request->getIsPost()) {
            $method = $this->request->getMethod();
            $url = $this->request->getAbsoluteUrl();
            $userAgent = $this->request->getUserAgent();
            $ip = $this->request->getUserIP();

            Cloudinary::log("Non-POST request received - Method: {$method}, URL: {$url}");
            Cloudinary::log("Request details - User-Agent: {$userAgent}, IP: {$ip}");

            Cloudinary::log("Non-POST webhook ignored, returning success");
            return $this->asSuccess();
        }

        $volumeId = $this->request->getRequiredQueryParam('volume');
        $notificationType = $this->request->getRequiredBodyParam('notification_type');

        Cloudinary::log("Webhook details - Volume ID: {$volumeId}, Notification Type: {$notificationType}");

        $sanitizedParams = Cloudinary::sanitizeParams($this->request->getBodyParams());
        Cloudinary::log("Request body params: " . json_encode($sanitizedParams));

        $fs = $this->verifyVolume($volumeId);
        $this->verifyCloudinarySignature($fs);

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
                createdAt: $this->request->getBodyParam('created_at'),
            ),

            default => Cloudinary::log("Unknown notification type: {$notificationType}", 'warning'),
        };

        Cloudinary::log("Webhook processing completed successfully");
        Cloudinary::log("=== End Webhook Request ===");

        return $this->asSuccess();
    }

    protected function verifyVolume($volumeId): CloudinaryFs
    {
        Cloudinary::log("Verifying volume with ID: {$volumeId}");

        $volume = Craft::$app->getVolumes()->getVolumeById($volumeId);

        if ($volume === null) {
            Cloudinary::log("Volume not found: {$volumeId}", 'error');
            throw new NotFoundHttpException('Volume not found');
        }

        Cloudinary::log("Volume found: {$volume->name}");

        $fs = $volume->getFs();

        if (!$fs instanceof CloudinaryFs) {
            Cloudinary::log("Invalid volume filesystem type: " . get_class($fs), 'error');
            throw new BadRequestHttpException('Invalid volume');
        }

        Cloudinary::log("Volume verified successfully");

        return $fs;
    }

    protected function verifyCloudinarySignature(CloudinaryFs $fs): void
    {
        Cloudinary::log("Verifying Cloudinary webhook signature");

        // Verify signature
        $apiSecret = App::parseEnv($fs->apiSecret);
        $body = $this->request->getRawBody();
        $timestamp = $this->request->getHeaders()->get('X-Cld-Timestamp');
        $signature = $this->request->getHeaders()->get('X-Cld-Signature');
        $signedPayload = $body . $timestamp;

        $maskedSignature = Cloudinary::maskSensitiveData($signature);
        Cloudinary::log("Signature verification - Timestamp: {$timestamp}, Received signature: {$maskedSignature}");

        $expectedSignature = sha1($signedPayload . $apiSecret);

        if ($expectedSignature !== $signature) {
            $maskedExpected = Cloudinary::maskSensitiveData($expectedSignature);
            Cloudinary::log("Signature mismatch - Expected: {$maskedExpected}, Received: {$maskedSignature}", 'error');
            throw new BadRequestHttpException('Invalid signature');
        }

        Cloudinary::log("Signature verified successfully");

        //To prevent against timing attacks, we compare the expected signature to each of the received signatures.
        if ($timestamp <= strtotime('-2 hours')) {
            //Signatures match, but older than 2 hours
            Cloudinary::log("Signature expired - Timestamp too old: {$timestamp}", 'error');
            throw new BadRequestHttpException('Expired signature');
        }

        Cloudinary::log("Timestamp is valid (within 2 hours)");
    }
}
