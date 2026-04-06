<?php

namespace Noo\CraftCloudinary\console\controllers;

use Craft;
use Noo\CraftCloudinary\fs\CloudinaryFs;
use yii\console\Controller;
use yii\console\ExitCode;

class ApiController extends Controller
{
    public function actionRateLimits(): int
    {
        $volumes = $this->getCloudinaryVolumes();

        if (empty($volumes)) {
            $this->stderr("No Cloudinary volumes found.\n");
            return ExitCode::DATAERR;
        }

        foreach ($volumes as $volume) {
            $fs = $volume->getFs();
            $response = $fs->getClient()->adminApi()->ping();

            $remaining = $response->rateLimitRemaining;
            $allowed = $response->rateLimitAllowed;
            $resetAt = $response->rateLimitResetAt;

            $this->stdout("Volume \"{$volume->name}\":\n");
            $this->stdout("  Rate limit: {$remaining}/{$allowed} remaining\n");

            if ($resetAt) {
                $resetFormatted = date('Y-m-d H:i:s T', $resetAt);
                $this->stdout("  Resets at:  {$resetFormatted}\n");
            }
        }

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
