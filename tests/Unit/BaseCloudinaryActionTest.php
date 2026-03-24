<?php

use Cloudinary\Asset\AssetType;
use Noo\CraftCloudinary\actions\BaseCloudinaryAction;

// BaseCloudinaryAction is abstract, so we need a concrete subclass for testing
class ConcreteAction extends BaseCloudinaryAction
{
}

describe('formatPath', function() {
    beforeEach(function() {
        $this->action = new ConcreteAction(volumeId: 1);
    });

    it('appends trailing slash to non-empty paths', function() {
        expect($this->action->formatPath('photos'))->toBe('photos/');
    });

    it('trims leading and trailing slashes and dots', function() {
        expect($this->action->formatPath('/photos/'))->toBe('photos/');
        expect($this->action->formatPath('./photos/.'))->toBe('photos/');
    });

    it('handles nested paths', function() {
        expect($this->action->formatPath('photos/vacation'))->toBe('photos/vacation/');
    });

    it('returns null for empty paths', function() {
        expect($this->action->formatPath(''))->toBeNull();
        expect($this->action->formatPath('/'))->toBeNull();
        expect($this->action->formatPath('.'))->toBeNull();
    });
});

describe('formatFilename', function() {
    beforeEach(function() {
        $this->action = new ConcreteAction(volumeId: 1);
    });

    it('appends format extension for image resources', function() {
        expect($this->action->formatFilename('my-photo', AssetType::IMAGE, 'jpg'))->toBe('my-photo.jpg');
    });

    it('appends format extension for video resources', function() {
        expect($this->action->formatFilename('clip', AssetType::VIDEO, 'mp4'))->toBe('clip.mp4');
    });

    it('does not append extension for raw resources', function() {
        expect($this->action->formatFilename('document.pdf', AssetType::RAW))->toBe('document.pdf');
    });

    it('uses wildcard format by default for non-raw', function() {
        expect($this->action->formatFilename('my-photo', AssetType::IMAGE))->toBe('my-photo.*');
    });

    it('extracts basename from paths with directories', function() {
        expect($this->action->formatFilename('photos/vacation/sunset', AssetType::IMAGE, 'jpg'))->toBe('sunset.jpg');
    });
});
