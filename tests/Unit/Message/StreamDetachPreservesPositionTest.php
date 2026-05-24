<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Message;

use InitPHP\HTTP\Message\Stream;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for B8: detaching a string-backend Stream must
 * preserve the in-memory cursor on the materialised resource.
 *
 * The legacy private helper (str2resorce typo) opened php://memory, wrote
 * the bytes, then rewound the handle to byte zero — so the caller observed
 * a brand-new resource positioned at the start of the buffer regardless of
 * where their cursor had been. The fixed implementation (stringToResource)
 * seeks the new handle to the same offset the string backend was sitting on.
 */
final class StreamDetachPreservesPositionTest extends TestCase
{
    public function testDetachReturnsResource(): void
    {
        $stream = new Stream('abcdef', null);
        $resource = $stream->detach();

        self::assertIsResource($resource, 'String-backend detach must materialise into a real resource');
        fclose($resource);
    }

    public function testDetachAtZeroPositionsResourceAtZero(): void
    {
        $stream = new Stream('abcdef', null);
        $resource = $stream->detach();

        self::assertSame(0, ftell($resource));
        rewind($resource);
        self::assertSame('abcdef', stream_get_contents($resource));
        fclose($resource);
    }

    public function testDetachAtMiddlePreservesCursor(): void
    {
        $stream = new Stream('abcdef', null);
        $stream->seek(3);
        $resource = $stream->detach();

        self::assertSame(3, ftell($resource), 'Materialised handle must inherit the in-memory cursor');
        self::assertSame('def', stream_get_contents($resource));
        fclose($resource);
    }

    public function testDetachAtEndPreservesCursor(): void
    {
        $stream = new Stream('abcdef', null);
        $stream->seek(6);
        $resource = $stream->detach();

        self::assertSame(6, ftell($resource));
        self::assertSame('', stream_get_contents($resource));
        fclose($resource);
    }

    public function testDetachClampsOutOfBoundsCursor(): void
    {
        // The in-memory seek() implementation already clamps to the buffer
        // length, but detach() carries its own clamp so a pathological
        // direct manipulation cannot punch past EOF on the new handle.
        $stream = new Stream('abc', null);
        $stream->seek(10); // clamped to 3 by Stream::str_seek()

        $resource = $stream->detach();
        self::assertLessThanOrEqual(3, ftell($resource));
        fclose($resource);
    }

    public function testDetachResourceBackedReturnsTheResourceVerbatim(): void
    {
        // Only the string backend materialises through stringToResource();
        // resource-backed streams hand the underlying resource back.
        $orig = fopen('php://temp', 'w+b');
        fwrite($orig, 'payload');
        rewind($orig);

        $stream = new Stream($orig);
        $detached = $stream->detach();

        self::assertSame($orig, $detached);
        fclose($detached);
    }

    public function testStateAfterDetachIsBrokenButSafe(): void
    {
        // After detach() the Stream wrapper is supposed to be unusable —
        // most operations throw RuntimeException, __toString returns ''.
        $stream = new Stream('seed', null);
        $resource = $stream->detach();

        // __toString must NOT throw even though the wrapper has nothing left.
        self::assertSame('', (string) $stream);

        // The detached resource still works on the caller's side.
        rewind($resource);
        self::assertSame('seed', stream_get_contents($resource));
        fclose($resource);
    }
}
