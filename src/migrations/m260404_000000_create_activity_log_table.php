<?php

namespace Noo\CraftCloudinary\migrations;

use craft\db\Migration;

class m260404_000000_create_activity_log_table extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->tableExists('{{%cloudinary_activity_log}}')) {
            $this->createTable('{{%cloudinary_activity_log}}', [
                'id' => $this->primaryKey(),
                'volumeId' => $this->integer(),
                'type' => $this->string(50)->notNull(),
                'message' => $this->string(255)->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
            ]);

            $this->createIndex(null, '{{%cloudinary_activity_log}}', ['dateCreated']);
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%cloudinary_activity_log}}');

        return true;
    }
}
