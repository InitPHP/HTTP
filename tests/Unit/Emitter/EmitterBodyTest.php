<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Emitter;

use InitPHP\HTTP\Emitter\Emitter;
use InitPHP\HTTP\Message\Response;
use InitPHP\HTTP\Message\Stream;
use PHPUnit\Framework\TestCase;

/**
 * Body emission: the two distinct paths in {@see Emitter::emit()}.
 *   - bufferLength=null: stream is echoed via __toString() in one shot.
 *   - bufferLength>0:    stream is chunked through read(bufferLength) in a
 *                        while(!eof) loop.
 *
 * Both paths must emit the same bytes; the chunked path additionally
 * rewinds the stream first so callers don't have to.
 */
final class EmitterBodyTest extends TestCase
{
    public function testDefaultEmitWritesEntireBody(): void
    {
        $response = new Response(200, [], 'the entire body');
        $emitter = new Emitter(false);

        ob_start();
        $emitter->emit($response);
        $output = (string) ob_get_clean();

        self::assertSame('the entire body', $output);
    }

    public function testChunkedEmitWritesEntireBody(): void
    {
        $payload = str_repeat('abcdefghij', 100); // 1000 bytes
        $response = new Response(200, [], new Stream($payload, 'php://temp'));
        $emitter = new Emitter(false);

        ob_start();
        $emitter->emit($response, 64); // 64-byte chunks
        $output = (string) ob_get_clean();

        self::assertSame($payload, $output);
    }

    public function testChunkedEmitRewindsBeforeReading(): void
    {
        // Body already at EOF before emit() runs. The chunked path must
        // rewind() it; otherwise the client sees an empty response.
        $stream = new Stream('rewind-me', 'php://temp');
        $stream->seek($stream->getSize() ?? 0); // park at EOF

        $response = new Response(200, [], $stream);
        $emitter = new Emitter(false);

        ob_start();
        $emitter->emit($response, 8);
        $output = (string) ob_get_clean();

        self::assertSame('rewind-me', $output);
    }

    public function testChunkedEmitWithBufferLargerThanBodyEmitsOnce(): void
    {
        $response = new Response(200, [], 'small');
        $emitter = new Emitter(false);

        ob_start();
        $emitter->emit($response, 1024);
        $output = (string) ob_get_clean();

        self::assertSame('small', $output);
    }

    public function testZeroBufferLengthFallsBackToDefaultEmit(): void
    {
        // bufferLength=0 fails the `> 0` guard and we take the echo path.
        $response = new Response(200, [], 'fallback');
        $emitter = new Emitter(false);

        ob_start();
        $emitter->emit($response, 0);
        $output = (string) ob_get_clean();

        self::assertSame('fallback', $output);
    }

    public function testNegativeBufferLengthFallsBackToDefaultEmit(): void
    {
        $response = new Response(200, [], 'fallback');
        $emitter = new Emitter(false);

        ob_start();
        $emitter->emit($response, -10);
        $output = (string) ob_get_clean();

        self::assertSame('fallback', $output);
    }
}
