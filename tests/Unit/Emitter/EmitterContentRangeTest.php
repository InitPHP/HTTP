<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Emitter;

use InitPHP\HTTP\Emitter\Emitter;
use InitPHP\HTTP\Message\Response;
use InitPHP\HTTP\Message\Stream;
use PHPUnit\Framework\TestCase;

/**
 * RFC 7233 Content-Range emission. When the response advertises a byte
 * range via `Content-Range: bytes <first>-<last>/<total>` and the caller
 * requests chunked emission, the emitter must:
 *
 *   - seek the body to <first>
 *   - emit exactly (last - first + 1) bytes
 *
 * The parseHeaderContentRange() / emitBodyRange() pair drives this.
 */
final class EmitterContentRangeTest extends TestCase
{
    public function testEmitsContiguousByteRange(): void
    {
        $payload = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'; // 26 bytes
        $body = new Stream($payload, 'php://temp');
        $response = (new Response(206, [], $body))
            ->withHeader('Content-Range', 'bytes 5-9/26');

        $emitter = new Emitter(false);
        ob_start();
        $emitter->emit($response, 4); // small chunk to exercise the loop
        $output = (string) ob_get_clean();

        // Bytes 5..9 inclusive => "FGHIJ"
        self::assertSame('FGHIJ', $output);
    }

    public function testEmitsByteRangeShorterThanBufferLength(): void
    {
        $payload = '0123456789';
        $body = new Stream($payload, 'php://temp');
        $response = (new Response(206, [], $body))
            ->withHeader('Content-Range', 'bytes 2-4/10');

        $emitter = new Emitter(false);
        ob_start();
        $emitter->emit($response, 64); // bufferLength > range length
        $output = (string) ob_get_clean();

        // Bytes 2..4 inclusive => "234"
        self::assertSame('234', $output);
    }

    public function testEmitsByteRangeWithExactBufferAlignment(): void
    {
        $payload = 'abcdefghij'; // 10 bytes
        $body = new Stream($payload, 'php://temp');
        $response = (new Response(206, [], $body))
            ->withHeader('Content-Range', 'bytes 0-7/10');

        $emitter = new Emitter(false);
        ob_start();
        $emitter->emit($response, 4); // 8 / 4 = 2 perfect iterations
        $output = (string) ob_get_clean();

        self::assertSame('abcdefgh', $output);
    }

    public function testEmitsByteRangeWithStarLength(): void
    {
        // `bytes 0-4/*` is legal RFC 7233 syntax when the total length is
        // unknown. parseHeaderContentRange() must accept '*' and the body
        // emission still honour the byte range.
        $payload = 'star-length-body';
        $body = new Stream($payload, 'php://temp');
        $response = (new Response(206, [], $body))
            ->withHeader('Content-Range', 'bytes 0-4/*');

        $emitter = new Emitter(false);
        ob_start();
        $emitter->emit($response, 2);
        $output = (string) ob_get_clean();

        self::assertSame('star-', $output);
    }

    public function testNonBytesUnitIsIgnoredAndFullBodyEmitted(): void
    {
        // Content-Range with a non-bytes unit (e.g. items) must not trigger
        // the byte-range emission path — the unit guard rejects it and we
        // fall through to the regular chunked emit.
        $payload = 'full-body';
        $body = new Stream($payload, 'php://temp');
        $response = (new Response(206, [], $body))
            ->withHeader('Content-Range', 'items 0-5/9');

        $emitter = new Emitter(false);
        ob_start();
        $emitter->emit($response, 16);
        $output = (string) ob_get_clean();

        self::assertSame('full-body', $output);
    }

    public function testMalformedContentRangeFallsThroughToFullEmission(): void
    {
        $payload = 'fallthrough';
        $body = new Stream($payload, 'php://temp');
        $response = (new Response(200, [], $body))
            ->withHeader('Content-Range', 'this is not a valid range');

        $emitter = new Emitter(false);
        ob_start();
        $emitter->emit($response, 8);
        $output = (string) ob_get_clean();

        self::assertSame('fallthrough', $output);
    }
}
