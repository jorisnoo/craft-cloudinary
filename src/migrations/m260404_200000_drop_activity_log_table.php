<?php

namespace Noo\CraftCloudinary\migrations;

use craft\db\Migration;

class m260404_200000_drop_activity_log_table extends Migration
{
    public function safeUp(): bool
    {
        $this->dropTableIfExists('{{%cloudinary_activity_log}}');

        return true;
    }

    public function safeDown(): bool
    {
        return true;
    }
}
