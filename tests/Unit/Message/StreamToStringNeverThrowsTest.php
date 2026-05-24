<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Message;

use InitPHP\HTTP\Message\Stream;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for B3: Stream::__toString() MUST NOT throw.
 *
 * PSR-7's MessageInterface\StreamInterface contract explicitly forbids
 * __toString from raising exceptions or errors. The legacy implementation
 * propagated RuntimeException out of getContents() when the stream was
 * detached or otherwise unhealthy, making `(string) $stream` a fatal in
 * emitters, templating layers, and log formatters.
 *
 * These tests pin the corrected behaviour: every failure mode returns an
 * empty string. If your code needs to distinguish "empty stream" from
 * "broken stream", call getContents() directly — that still throws.
 */
final class StreamToStringNeverThrowsTest extends TestCase
{
    public function testToStringOfClosedTempStreamReturnsEmptyString(): void
    {
        $stream = new Stream('payload', 'php://temp');
        $stream->close();

        $out = (string) $stream;

        self::assertSame('', $out);
    }

    public function testToStringOfDetachedStreamReturnsEmptyString(): void
    {
        $stream = new Stream('payload', 'php://temp');
        $resource = $stream->detach();

        // Detached: subsequent operations on $stream are no-ops on the
        // resource the caller now owns. __toString must not surface the
        // RuntimeException that getContents() raises in this state.
        $out = (string) $stream;

        self::assertSame('', $out);

        if (is_resource($resource)) {
            fclose($resource);
        }
    }

    public function testToStringOfClosedStringBackendStreamReturnsEmptyString(): void
    {
        $stream = new Stream('text', null);
        $stream->close();

        self::assertSame('', (string) $stream);
    }

    public function testToStringRewindsBeforeReadingForSeekableStreams(): void
    {
        // Even when the cursor is at EOF, __toString rewinds first so the
        // caller observes the full payload. This is the happy-path counterpart
        // to the "never throws" guarantee: failure returns ''; success returns
        // the whole thing.
        $stream = new Stream('full body', 'php://temp');
        $stream->seek(strlen('full body'));

        self::assertSame('full body', (string) $stream);
    }

    public function testToStringOfFreshTempStreamReturnsSeeded(): void
    {
        $stream = new Stream('seed', 'php://temp');
        self::assertSame('seed', (string) $stream);
    }

    public function testGetContentsStillThrowsOnClosedStream(): void
    {
        // The "never throws" guarantee applies to __toString *only*.
        // getContents() retains its strict failure surface so callers who
        // want a hard error keep one.
        $stream = new Stream('x', 'php://temp');
        $stream->close();

        $this->expectException(\RuntimeException::class);
        $stream->getContents();
    }
}
