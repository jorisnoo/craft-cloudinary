<?php

use Noo\CraftCloudinary\helpers\WebhookSignature;
use yii\web\BadRequestHttpException;

describe('WebhookSignature::verify', function() {
    it('accepts a valid signature', function() {
        $body = '{"notification_type":"upload"}';
        $secret = 'my-api-secret';
        $timestamp = (string) time();
        $signature = sha1($body . $timestamp . $secret);

        WebhookSignature::verify($body, $timestamp, $signature, $secret);

        expect(true)->toBeTrue();
    });

    it('rejects an invalid signature', function() {
        $body = '{"notification_type":"upload"}';
        $secret = 'my-api-secret';
        $timestamp = (string) time();
        $signature = 'invalid-signature-hash';

        WebhookSignature::verify($body, $timestamp, $signature, $secret);
    })->throws(BadRequestHttpException::class, 'Invalid signature');

    it('rejects a signature with wrong secret', function() {
        $body = '{"notification_type":"upload"}';
        $secret = 'my-api-secret';
        $wrongSecret = 'wrong-secret';
        $timestamp = (string) time();
        $signature = sha1($body . $timestamp . $wrongSecret);

        WebhookSignature::verify($body, $timestamp, $signature, $secret);
    })->throws(BadRequestHttpException::class, 'Invalid signature');

    it('rejects a signature with tampered body', function() {
        $body = '{"notification_type":"upload"}';
        $secret = 'my-api-secret';
        $timestamp = (string) time();
        $signature = sha1($body . $timestamp . $secret);

        $tamperedBody = '{"notification_type":"delete"}';

        WebhookSignature::verify($tamperedBody, $timestamp, $signature, $secret);
    })->throws(BadRequestHttpException::class, 'Invalid signature');

    it('rejects expired signatures before checking the hash', function() {
        $body = '{"notification_type":"upload"}';
        $secret = 'my-api-secret';
        $timestamp = (string) (time() - 7201);
        // Use a wrong secret so the hash would also fail —
        // we expect "Expired signature", not "Invalid signature"
        $signature = sha1($body . $timestamp . 'wrong-secret');

        WebhookSignature::verify($body, $timestamp, $signature, $secret);
    })->throws(BadRequestHttpException::class, 'Expired signature');

    it('accepts signatures just within the 2 hour window', function() {
        $body = '{"notification_type":"upload"}';
        $secret = 'my-api-secret';
        $timestamp = (string) (time() - 3600);
        $signature = sha1($body . $timestamp . $secret);

        WebhookSignature::verify($body, $timestamp, $signature, $secret);

        expect(true)->toBeTrue();
    });
});

describe('WebhookSignature::verifyTimestamp', function() {
    it('accepts a current timestamp', function() {
        WebhookSignature::verifyTimestamp((string) time());

        expect(true)->toBeTrue();
    });

    it('accepts a timestamp within the 2 hour window', function() {
        WebhookSignature::verifyTimestamp((string) (time() - 3600));

        expect(true)->toBeTrue();
    });

    it('rejects a timestamp older than 2 hours', function() {
        WebhookSignature::verifyTimestamp((string) (time() - 7201));
    })->throws(BadRequestHttpException::class, 'Expired signature');
});
