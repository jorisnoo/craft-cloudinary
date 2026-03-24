<?php

use Noo\CraftCloudinary\Cloudinary;

describe('maskSensitiveData', function() {
    it('masks long strings showing first 4 characters', function() {
        expect(Cloudinary::maskSensitiveData('abcdefghijklmnop'))->toBe('abcd********');
    });

    it('masks strings shorter than visible chars entirely', function() {
        expect(Cloudinary::maskSensitiveData('abc'))->toBe('***');
    });

    it('masks strings equal to visible chars entirely', function() {
        expect(Cloudinary::maskSensitiveData('abcd'))->toBe('****');
    });

    it('respects custom visible char count', function() {
        expect(Cloudinary::maskSensitiveData('abcdefgh', 2))->toBe('ab******');
    });

    it('caps mask length at 8 asterisks', function() {
        $long = str_repeat('x', 100);
        $masked = Cloudinary::maskSensitiveData($long);
        expect($masked)->toBe('xxxx********');
    });

    it('handles empty strings', function() {
        expect(Cloudinary::maskSensitiveData(''))->toBe('');
    });
});

describe('sanitizeParams', function() {
    it('masks exact sensitive keys', function() {
        $params = [
            'key' => 'my-secret-key',
            'password' => 'hunter2',
            'name' => 'visible',
        ];

        $sanitized = Cloudinary::sanitizeParams($params);

        expect($sanitized['key'])->not->toBe('my-secret-key');
        expect($sanitized['password'])->not->toBe('hunter2');
        expect($sanitized['name'])->toBe('visible');
    });

    it('masks compound sensitive keys', function() {
        $params = [
            'api_key' => 'abc123456789',
            'api_secret' => 'secret789',
            'x-api-key' => 'header-key',
        ];

        $sanitized = Cloudinary::sanitizeParams($params);

        expect($sanitized['api_key'])->not->toBe('abc123456789');
        expect($sanitized['api_secret'])->not->toBe('secret789');
        expect($sanitized['x-api-key'])->not->toBe('header-key');
    });

    it('is case-insensitive for key matching', function() {
        $params = [
            'API_KEY' => 'value1',
            'Password' => 'value2',
            'SIGNATURE' => 'value3',
        ];

        $sanitized = Cloudinary::sanitizeParams($params);

        expect($sanitized['API_KEY'])->not->toBe('value1');
        expect($sanitized['Password'])->not->toBe('value2');
        expect($sanitized['SIGNATURE'])->not->toBe('value3');
    });

    it('leaves non-sensitive keys untouched', function() {
        $params = [
            'public_id' => 'my-image',
            'resource_type' => 'image',
            'notification_type' => 'upload',
        ];

        $sanitized = Cloudinary::sanitizeParams($params);

        expect($sanitized)->toBe($params);
    });
});
