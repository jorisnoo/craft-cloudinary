<?php

describe('Webhook signature hashing', function() {
    it('produces consistent hashes for the same signature', function() {
        $signature = 'abc123def456';

        $hash1 = hash('sha256', $signature);
        $hash2 = hash('sha256', $signature);

        expect($hash1)->toBe($hash2);
    });

    it('produces different hashes for different signatures', function() {
        $hash1 = hash('sha256', 'signature-a');
        $hash2 = hash('sha256', 'signature-b');

        expect($hash1)->not->toBe($hash2);
    });

    it('produces a 64-character hex string', function() {
        $hash = hash('sha256', 'any-signature');

        expect(strlen($hash))->toBe(64);
        expect(ctype_xdigit($hash))->toBeTrue();
    });
});
