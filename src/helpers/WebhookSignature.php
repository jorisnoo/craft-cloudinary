<?php

namespace Noo\CraftCloudinary\helpers;

use yii\web\BadRequestHttpException;

class WebhookSignature
{
    public static function verify(string $body, ?string $timestamp, ?string $signature, string $apiSecret): void
    {
        if ($timestamp === null || $timestamp === '' || $signature === null || $signature === '') {
            throw new BadRequestHttpException('Missing signature');
        }

        $expectedSignature = sha1($body . $timestamp . $apiSecret);

        if (!hash_equals($expectedSignature, $signature)) {
            throw new BadRequestHttpException('Invalid signature');
        }
    }

    public static function verifyTimestamp(?string $timestamp): void
    {
        if ($timestamp === null || $timestamp === '') {
            throw new BadRequestHttpException('Missing timestamp');
        }

        if ((int) $timestamp <= strtotime('-2 hours')) {
            throw new BadRequestHttpException('Expired signature');
        }
    }
}
