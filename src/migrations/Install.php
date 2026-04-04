<?php

namespace Noo\CraftCloudinary\migrations;

use craft\db\Migration;

class Install extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->tableExists('{{%cloudinary_webhook_log}}')) {
            $this->createTable('{{%cloudinary_webhook_log}}', [
                'id' => $this->primaryKey(),
                'signatureHash' => $this->string(64)->notNull(),
                'notificationType' => $this->string(50)->notNull(),
                'publicId' => $this->string(255),
                'cloudinaryTimestamp' => $this->integer()->notNull(),
                'processedAt' => $this->dateTime()->notNull(),
            ]);

            $this->createIndex(null, '{{%cloudinary_webhook_log}}', ['signatureHash'], true);
            $this->createIndex(null, '{{%cloudinary_webhook_log}}', ['processedAt']);
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%cloudinary_webhook_log}}');
        $this->dropTableIfExists('{{%cloudinary_activity_log}}');

        return true;
    }
}
