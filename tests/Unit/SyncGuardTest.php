<?php

use Noo\CraftCloudinary\services\SyncGuard;

describe('SyncGuard processing flags', function() {
    it('is not processing webhook by default', function() {
        $guard = new SyncGuard();

        expect($guard->isProcessingWebhook())->toBeFalse();
    });

    it('sets webhook flag during callback', function() {
        $guard = new SyncGuard();
        $wasProcessing = false;

        $guard->whileProcessingWebhook(function() use ($guard, &$wasProcessing) {
            $wasProcessing = $guard->isProcessingWebhook();
        });

        expect($wasProcessing)->toBeTrue();
        expect($guard->isProcessingWebhook())->toBeFalse();
    });

    it('returns the callback result', function() {
        $guard = new SyncGuard();

        $result = $guard->whileProcessingWebhook(fn() => 'hello');

        expect($result)->toBe('hello');
    });

    it('clears flag even if callback throws', function() {
        $guard = new SyncGuard();

        try {
            $guard->whileProcessingWebhook(function() {
                throw new \RuntimeException('test');
            });
        } catch (\RuntimeException) {
        }

        expect($guard->isProcessingWebhook())->toBeFalse();
    });
});
