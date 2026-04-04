<?php

namespace Noo\CraftCloudinary\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int|null $volumeId
 * @property string $type
 * @property string $message
 * @property string $dateCreated
 */
class ActivityLogRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%cloudinary_activity_log}}';
    }
}
