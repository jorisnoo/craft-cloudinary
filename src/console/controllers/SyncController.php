<?php

namespace Noo\CraftCloudinary\console\controllers;

use Craft;
use Noo\CraftCloudinary\Cloudinary;
use Noo\CraftCloudinary\exceptions\ReconciliationAbortedException;
use Noo\CraftCloudinary\fs\CloudinaryFs;
use yii\console\Controller;
use yii\console\ExitCode;

class SyncController extends Controller
{
    public bool $dryRun = false;

    public bool $force = false;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['dryRun', 'force']);
    }

    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), ['d' => 'dryRun', 'f' => 'force']);
    }

    public function actionIndex(): int
    {
        $volumes = $this->getCloudinaryVolumes();

        if (empty($volumes)) {
            $this->stderr("No Cloudinary volumes found.\n");
            return ExitCode::DATAERR;
        }

        $reconciler = Cloudinary::getInstance()->syncReconciler;

        if ($this->dryRun) {
            $this->stdout("DRY RUN — no changes will be written.\n");
        }

        $aborts = 0;

        foreach ($volumes as $volume) {
            $this->stdout("Syncing volume \"{$volume->name}\"...\n");

            try {
                $stats = $reconciler->reconcile($volume->id, $this->dryRun, $this->force);
            } catch (ReconciliationAbortedException $e) {
                $aborts++;
                $this->stderr("  ABORTED ({$e->reason}): would delete {$e->wouldDelete}/{$e->craftCount}. Check logs for asset IDs.\n");
                if ($e->reason === ReconciliationAbortedException::REASON_DELETION_RATIO) {
                    $this->stderr("  Re-run with --force to override the ratio guard.\n");
                }
                continue;
            }

            $this->stdout("  Created: {$stats['created']}\n");
            $this->stdout("  Deleted: {$stats['deleted']}\n");
            $this->stdout("  Updated: {$stats['updated']}\n");
            $this->stdout("  Unchanged: {$stats['unchanged']}\n");
        }

        if ($aborts > 0) {
            $this->stderr("Sync finished with {$aborts} aborted volume(s).\n");
            return ExitCode::SOFTWARE;
        }

        $this->stdout("Sync complete.\n");

        return ExitCode::OK;
    }

    private function getCloudinaryVolumes(): array
    {
        return array_filter(
            Craft::$app->getVolumes()->getAllVolumes(),
            fn($v) => $v->getFs() instanceof CloudinaryFs,
        );
    }
}
