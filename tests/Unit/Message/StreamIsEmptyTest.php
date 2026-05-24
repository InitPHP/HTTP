<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Message;

use InitPHP\HTTP\Message\Stream;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for B10: Stream::isEmpty()/isNotEmpty() must treat an
 * unknown size as indeterminate, not as empty.
 *
 * The legacy implementation used `getSize() < 1`, which in PHP evaluates to
 * `null < 1 === true` and mis-classified every pipe/socket/chunked-response
 * stream as empty. The corrected implementation returns true only when the
 * size is known and strictly less than 1 (for isEmpty) or strictly greater
 * than 0 (for isNotEmpty); both return false in the indeterminate case so
 * callers can branch on "I don't know" explicitly.
 */
final class StreamIsEmptyTest extends TestCase
{
    public function testZeroSizedStreamReportsEmpty(): void
    {
        $stream = new Stream('', null);
        self::assertTrue($stream->isEmpty());
        self::assertFalse($stream->isNotEmpty());
    }

    public function testNonEmptyStringStreamReportsNotEmpty(): void
    {
        $stream = new Stream('hello', null);
        self::assertFalse($stream->isEmpty());
        self::assertTrue($stream->isNotEmpty());
    }

    public function testUnknownSizeIsIndeterminate(): void
    {
        // Wrap a stream whose size cannot be reported by fstat() — anonymous
        // pipes are the canonical example. After detach() the size resets to
        // null too; we use that path to provoke the "size unknown" state in
        // a portable way.
        $resource = fopen('php://temp', 'w+b');
        self::assertIsResource($resource);
        fwrite($resource, 'pipeable');

        $stream = new Stream($resource);
        $stream->detach();

        // Detached: size is null, and isEmpty/isNotEmpty must both report false.
        self::assertFalse($stream->isEmpty(), 'Detached stream is indeterminate, not empty');
        self::assertFalse($stream->isNotEmpty(), 'Detached stream is indeterminate, not non-empty');
    }

    public function testTempBackedNonEmptyStreamReportsNotEmpty(): void
    {
        $stream = new Stream('content', 'php://temp');
        self::assertFalse($stream->isEmpty());
        self::assertTrue($stream->isNotEmpty());
    }

    public function testEmptyTempStreamReportsEmpty(): void
    {
        $stream = new Stream('', 'php://temp');
        self::assertTrue($stream->isEmpty());
        self::assertFalse($stream->isNotEmpty());
    }
}
