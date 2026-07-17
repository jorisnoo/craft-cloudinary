<?php

use Noo\CraftCloudinary\helpers\FsListings;

function makeResource(array $overrides = []): array
{
    return array_merge([
        'public_id' => 'photo',
        'asset_folder' => '',
        'resource_type' => 'image',
        'format' => 'jpg',
        'bytes' => 1024,
        'created_at' => '2026-07-01T12:00:00Z',
    ], $overrides);
}

describe('FsListings::fromResources', function() {
    it('lists a root file with metadata', function() {
        $listings = iterator_to_array(FsListings::fromResources([makeResource()], '', true), false);

        expect($listings)->toHaveCount(1);
        expect($listings[0]->getUri())->toBe('photo.jpg');
        expect($listings[0]->getIsDir())->toBeFalse();
        expect($listings[0]->getFileSize())->toBe(1024);
        expect($listings[0]->getDateModified())->toBe(strtotime('2026-07-01T12:00:00Z'));
    });

    it('yields ancestor directories before the file, once each', function() {
        $resources = [
            makeResource(['public_id' => 'one', 'asset_folder' => 'a/b']),
            makeResource(['public_id' => 'two', 'asset_folder' => 'a/b']),
        ];

        $listings = iterator_to_array(FsListings::fromResources($resources, '', true), false);
        $uris = array_map(fn($l) => ($l->getIsDir() ? 'dir:' : 'file:') . $l->getUri(), $listings);

        expect($uris)->toBe(['dir:a', 'dir:a/b', 'file:a/b/one.jpg', 'file:a/b/two.jpg']);
    });

    it('keeps paths absolute when listing a subdirectory', function() {
        $resources = [
            makeResource(['public_id' => 'root-level', 'asset_folder' => 'sub']),
            makeResource(['public_id' => 'nested', 'asset_folder' => 'sub/x']),
        ];

        $listings = iterator_to_array(FsListings::fromResources($resources, 'sub', true), false);
        $uris = array_map(fn($l) => ($l->getIsDir() ? 'dir:' : 'file:') . $l->getUri(), $listings);

        expect($uris)->toBe(['file:sub/root-level.jpg', 'dir:sub/x', 'file:sub/x/nested.jpg']);
    });

    it('skips resources outside the listed directory', function() {
        $resources = [
            makeResource(['asset_folder' => 'elsewhere']),
            makeResource(['asset_folder' => 'sub-not-really']),
        ];

        $listings = iterator_to_array(FsListings::fromResources($resources, 'sub', true), false);

        expect($listings)->toBeEmpty();
    });

    it('does not append the format to raw files', function() {
        $resources = [makeResource([
            'public_id' => 'document.pdf',
            'resource_type' => 'raw',
            'format' => 'pdf',
        ])];

        $listings = iterator_to_array(FsListings::fromResources($resources, '', true), false);

        expect($listings[0]->getUri())->toBe('document.pdf');
    });

    it('uses the basename when the public_id contains a path', function() {
        $resources = [makeResource([
            'public_id' => 'legacy/path/image',
            'asset_folder' => 'photos',
        ])];

        $listings = iterator_to_array(FsListings::fromResources($resources, '', true), false);
        $files = array_values(array_filter($listings, fn($l) => !$l->getIsDir()));

        expect($files[0]->getUri())->toBe('photos/image.jpg');
    });

    it('only lists direct children when not recursive', function() {
        $resources = [
            makeResource(['public_id' => 'direct', 'asset_folder' => 'sub']),
            makeResource(['public_id' => 'nested', 'asset_folder' => 'sub/child/deep']),
        ];

        $listings = iterator_to_array(FsListings::fromResources($resources, 'sub', false), false);
        $uris = array_map(fn($l) => ($l->getIsDir() ? 'dir:' : 'file:') . $l->getUri(), $listings);

        expect($uris)->toBe(['file:sub/direct.jpg', 'dir:sub/child']);
    });

    it('handles missing optional fields', function() {
        $resources = [[
            'public_id' => 'bare',
            'resource_type' => 'image',
        ]];

        $listings = iterator_to_array(FsListings::fromResources($resources, '', true), false);

        expect($listings[0]->getUri())->toBe('bare');
        expect($listings[0]->getFileSize())->toBeNull();
        expect($listings[0]->getDateModified())->toBeNull();
    });
});
