<?php

namespace Noo\CraftCloudinary\services;

use Craft;
use craft\db\Query;
use DateInterval;
use DateTime;
use Noo\CraftCloudinary\records\ActivityLogRecord;
use yii\base\Component;

class ActivityLog extends Component
{
    public function log(string $type, string $message, ?int $volumeId = null): void
    {
        $record = new ActivityLogRecord();
        $record->type = $type;
        $record->message = $message;
        $record->volumeId = $volumeId;
        $record->dateCreated = (new DateTime())->format('Y-m-d H:i:s');
        $record->save(false);

        // Auto-prune entries older than 30 days (1 in 20 chance to avoid running every insert)
        if (random_int(1, 20) === 1) {
            $this->prune();
        }
    }

    public function getRecent(int $limit = 50): array
    {
        return (new Query())
            ->select(['log.id', 'log.type', 'log.message', 'log.dateCreated', 'volumes.name as volumeName'])
            ->from(['log' => '{{%cloudinary_activity_log}}'])
            ->leftJoin(['volumes' => '{{%volumes}}'], '[[volumes.id]] = [[log.volumeId]]')
            ->orderBy(['log.dateCreated' => SORT_DESC])
            ->limit($limit)
            ->all();
    }

    public function prune(int $maxAgeDays = 30): int
    {
        $threshold = (new DateTime())->sub(new DateInterval("P{$maxAgeDays}D"))->format('Y-m-d H:i:s');

        return Craft::$app->getDb()->createCommand()
            ->delete('{{%cloudinary_activity_log}}', ['<', 'dateCreated', $threshold])
            ->execute();
    }
}
