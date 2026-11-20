<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Noo\CraftCloudinary\helpers\RawImageDimensions;

function rawImageDimensionsClient(array $responses, array &$history): Client
{
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));

    return new Client(['handler' => $stack]);
}

function pngBytes(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

describe('RawImageDimensions', function() {
    it('reads dimensions from the start of the file', function() {
        $history = [];
        $client = rawImageDimensionsClient([new Response(206, [], pngBytes(40, 30))], $history);

        expect(RawImageDimensions::fetch('https://res.cloudinary.com/demo/raw/upload/scan.PNG', $client))->toBe([40, 30])
            ->and($history[0]['request']->getHeaderLine('Range'))->toBe('bytes=0-262143');
    });

    it('returns null for files that are not images', function() {
        $history = [];
        $client = rawImageDimensionsClient([new Response(200, [], 'not an image')], $history);

        expect(RawImageDimensions::fetch('https://res.cloudinary.com/demo/raw/upload/a.JPG', $client))->toBeNull();
    });

    it('returns null when the request fails', function() {
        $history = [];
        $client = rawImageDimensionsClient([new Response(404)], $history);

        expect(RawImageDimensions::fetch('https://res.cloudinary.com/demo/raw/upload/a.JPG', $client))->toBeNull();
    });

    it('only reads formats getimagesize supports', function() {
        expect(RawImageDimensions::isReadable('photos/scan.JPG'))->toBeTrue()
            ->and(RawImageDimensions::isReadable('logo.SVG'))->toBeFalse()
            ->and(RawImageDimensions::isReadable('data.zip'))->toBeFalse();
    });
});
