<?php

namespace Noo\CraftCloudinary\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property string $signatureHash
 * @property string $notificationType
 * @property string|null $publicId
 * @property int $cloudinaryTimestamp
 * @property string $processedAt
 */
class WebhookLogRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%cloudinary_webhook_log}}';
    }
}
