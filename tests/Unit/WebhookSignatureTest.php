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

        // No exception means success
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

    it('rejects expired signatures older than 2 hours', function() {
        $body = '{"notification_type":"upload"}';
        $secret = 'my-api-secret';
        $timestamp = (string) (time() - 7201); // 2 hours + 1 second ago
        $signature = sha1($body . $timestamp . $secret);

        WebhookSignature::verify($body, $timestamp, $signature, $secret);
    })->throws(BadRequestHttpException::class, 'Expired signature');

    it('accepts signatures just within the 2 hour window', function() {
        $body = '{"notification_type":"upload"}';
        $secret = 'my-api-secret';
        $timestamp = (string) (time() - 3600); // 1 hour ago
        $signature = sha1($body . $timestamp . $secret);

        WebhookSignature::verify($body, $timestamp, $signature, $secret);

        expect(true)->toBeTrue();
    });
});
