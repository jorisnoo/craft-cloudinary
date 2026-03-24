<?php

namespace Noo\CraftCloudinary\helpers;

use yii\web\BadRequestHttpException;

class WebhookSignature
{
    public static function verify(string $body, string $timestamp, string $signature, string $apiSecret): void
    {
        $expectedSignature = sha1($body . $timestamp . $apiSecret);

        if (!hash_equals($expectedSignature, $signature)) {
            throw new BadRequestHttpException('Invalid signature');
        }

        if ((int) $timestamp <= strtotime('-2 hours')) {
            throw new BadRequestHttpException('Expired signature');
        }
    }
}
