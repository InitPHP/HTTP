<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Message;

use InitPHP\HTTP\Message\Stream;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for the string-backend Stream::write() rewrite (B2).
 *
 * The legacy implementation:
 *   - prepended at position 0 (`$string . $this->stream`),
 *   - never advanced $this->seek after a write,
 * so a freshly-constructed stream's write(' world') produced ' worldhello'
 * instead of overwriting from the current cursor, and `tell()` always
 * reported zero. Both behaviours diverge from fwrite() on a real handle.
 *
 * These tests pin the corrected semantics: substring overwrite-or-extend
 * with the cursor advancing by the number of bytes actually written.
 */
final class StreamStringBackendWriteTest extends TestCase
{
    public function testWriteFromZeroOverwritesAndExtends(): void
    {
        // "hello" is 5 bytes; writing " world" (6 bytes) from position 0
        // overwrites all of it and extends one byte past — yielding " world".
        $stream = new Stream('hello', null);
        $written = $stream->write(' world');

        self::assertSame(6, $written);
        self::assertSame(6, $stream->tell());
        self::assertSame(' world', (string) $stream);
    }

    public function testWriteFromMiddleOverwritesInPlace(): void
    {
        $stream = new Stream('hello world', null);
        $stream->seek(6);
        $written = $stream->write('there');

        self::assertSame(5, $written);
        self::assertSame(11, $stream->tell());
        self::assertSame('hello there', (string) $stream);
    }

    public function testWriteBeyondEofAppends(): void
    {
        $stream = new Stream('abc', null);
        $stream->seek(3);
        $written = $stream->write('def');

        self::assertSame(3, $written);
        self::assertSame(6, $stream->tell());
        self::assertSame('abcdef', (string) $stream);
    }

    public function testWriteAtEofExtendsWithoutCorruption(): void
    {
        $stream = new Stream('first', null);
        // Seek past EOF — the implementation clamps to size, then appends.
        $stream->seek(100);
        $stream->write('-second');

        // Check tell() before casting: __toString() rewinds, then tell()
        // would always report 0.
        self::assertSame(strlen('first-second'), $stream->tell());
        self::assertSame('first-second', (string) $stream);
    }

    public function testEmptyWriteIsNoOpOnContentButValid(): void
    {
        $stream = new Stream('keep', null);
        $written = $stream->write('');

        self::assertSame(0, $written);
        self::assertSame('keep', (string) $stream);
    }

    public function testTellAdvancesAfterMultipleWrites(): void
    {
        $stream = new Stream('', null);
        $stream->write('alpha');
        $stream->write('-');
        $stream->write('beta');

        // tell() before cast (cast rewinds for seekable streams).
        self::assertSame(10, $stream->tell());
        self::assertSame('alpha-beta', (string) $stream);
    }

    public function testRewindThenWriteOverwrites(): void
    {
        $stream = new Stream('original', null);
        $stream->seek(strlen('original')); // park at EOF
        self::assertSame(strlen('original'), $stream->tell());

        $stream->rewind();
        self::assertSame(0, $stream->tell());

        $stream->write('CHANGE');
        self::assertSame(6, $stream->tell());          // before cast (cast rewinds)
        self::assertSame('CHANGEal', (string) $stream);
    }
}
