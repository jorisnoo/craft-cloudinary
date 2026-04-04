<?php

namespace Noo\CraftCloudinary\controllers;

use Craft;
use craft\helpers\App;
use craft\web\Controller;
use DateTime;
use Noo\CraftCloudinary\actions\AssetChangeDisplayNameAction;
use Noo\CraftCloudinary\actions\AssetDeleteAction;
use Noo\CraftCloudinary\actions\AssetMoveAction;
use Noo\CraftCloudinary\actions\AssetRenameAction;
use Noo\CraftCloudinary\actions\AssetUploadAction;
use Noo\CraftCloudinary\actions\FolderCreateAction;
use Noo\CraftCloudinary\actions\FolderDeleteAction;
use Noo\CraftCloudinary\actions\FolderRenameAction;
use Noo\CraftCloudinary\Cloudinary;
use Noo\CraftCloudinary\fs\CloudinaryFs;
use Noo\CraftCloudinary\helpers\WebhookSignature;
use Noo\CraftCloudinary\records\WebhookLogRecord;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class NotificationsController extends Controller
{
    public $enableCsrfValidation = false;

    protected array|bool|int $allowAnonymous = true;

    // /actions/cloudinary/notifications/process or /actions/cloudinary/notifications/process?volume=1
    public function actionProcess(): Response
    {
        if (!$this->request->getIsPost()) {
            return $this->asSuccess();
        }

        $notificationType = $this->request->getRequiredBodyParam('notification_type');
        $volumeId = $this->request->getQueryParam('volume');

        if ($volumeId) {
            $fs = $this->verifyVolume($volumeId);
            $this->verifyCloudinarySignature($fs);
        } else {
            [$volumeId, $fs] = $this->resolveVolumeFromSignature();
        }

        $signature = $this->request->getHeaders()->get('X-Cld-Signature');
        $timestamp = (int) $this->request->getHeaders()->get('X-Cld-Timestamp');

        if ($this->isDuplicateWebhook($signature)) {
            return $this->asSuccess();
        }

        $publicId = $this->request->getBodyParam('public_id');

        Cloudinary::getInstance()->syncGuard->whileProcessingWebhook(function() use ($notificationType, $volumeId) {
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
        });

        $this->logWebhook($signature, $notificationType, $publicId, $timestamp);

        return $this->asSuccess();
    }

    protected function isDuplicateWebhook(?string $signature): bool
    {
        if ($signature === null) {
            return false;
        }

        $hash = hash('sha256', $signature);

        return WebhookLogRecord::find()
            ->where(['signatureHash' => $hash])
            ->exists();
    }

    protected function logWebhook(?string $signature, string $notificationType, ?string $publicId, int $timestamp): void
    {
        if ($signature === null) {
            return;
        }

        $record = new WebhookLogRecord();
        $record->signatureHash = hash('sha256', $signature);
        $record->notificationType = $notificationType;
        $record->publicId = $publicId;
        $record->cloudinaryTimestamp = $timestamp;
        $record->processedAt = (new DateTime())->format('Y-m-d H:i:s');
        $record->save(false);

        // Prune entries older than 48 hours (1 in 10 chance)
        if (random_int(1, 10) === 1) {
            Craft::$app->getDb()->createCommand()
                ->delete('{{%cloudinary_webhook_log}}', ['<', 'processedAt', (new DateTime('-48 hours'))->format('Y-m-d H:i:s')])
                ->execute();
        }
    }

    protected function verifyVolume($volumeId): CloudinaryFs
    {
        $volume = Craft::$app->getVolumes()->getVolumeById($volumeId);

        if ($volume === null) {
            Cloudinary::log("Volume not found: {$volumeId}", 'error');
            throw new NotFoundHttpException('Volume not found');
        }

        $fs = $volume->getFs();

        if (!$fs instanceof CloudinaryFs) {
            Cloudinary::log("Invalid volume filesystem type: " . get_class($fs), 'error');
            throw new BadRequestHttpException('Invalid volume');
        }

        return $fs;
    }

    protected function resolveVolumeFromSignature(): array
    {
        $body = $this->request->getRawBody();
        $timestamp = $this->request->getHeaders()->get('X-Cld-Timestamp');
        $signature = $this->request->getHeaders()->get('X-Cld-Signature');

        // Fail fast on expired timestamps before looping volumes
        WebhookSignature::verifyTimestamp($timestamp);

        $volumes = Craft::$app->getVolumes()->getAllVolumes();

        foreach ($volumes as $volume) {
            $fs = $volume->getFs();

            if (!$fs instanceof CloudinaryFs) {
                continue;
            }

            $apiSecret = App::parseEnv($fs->apiSecret);

            try {
                WebhookSignature::verify($body, $timestamp, $signature, $apiSecret);

                return [$volume->id, $fs];
            } catch (BadRequestHttpException) {
                continue;
            }
        }

        Cloudinary::log('Webhook signature did not match any Cloudinary volume', 'error');
        throw new BadRequestHttpException('Invalid signature');
    }

    protected function verifyCloudinarySignature(CloudinaryFs $fs): void
    {
        $apiSecret = App::parseEnv($fs->apiSecret);
        $body = $this->request->getRawBody();
        $timestamp = $this->request->getHeaders()->get('X-Cld-Timestamp');
        $signature = $this->request->getHeaders()->get('X-Cld-Signature');

        try {
            WebhookSignature::verifyTimestamp($timestamp);
            WebhookSignature::verify($body, $timestamp, $signature, $apiSecret);
        } catch (BadRequestHttpException $e) {
            Cloudinary::log("Webhook signature verification failed: {$e->getMessage()}", 'error');
            throw $e;
        }
    }
}
