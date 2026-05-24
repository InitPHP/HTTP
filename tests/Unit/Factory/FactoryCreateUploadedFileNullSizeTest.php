<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Factory;

use InitPHP\HTTP\Factory\Factory;
use InitPHP\HTTP\Message\Stream;
use PHPUnit\Framework\TestCase;

/**
 * Regression B7: createUploadedFile() must derive the size from the
 * supplied stream when the caller passes `$size === null`. The original
 * code forwarded null straight to UploadedFile, which then surfaced
 * getSize() === null even for streams whose getSize() returned an integer.
 *
 * The fix: when $size is null the factory consults $stream->getSize()
 * before constructing the UploadedFile.
 */
final class FactoryCreateUploadedFileNullSizeTest extends TestCase
{
    public function testNullSizeIsDerivedFromTheStream(): void
    {
        $payload = 'twelve-bytes';
        $stream = new Stream($payload, 'php://temp');

        $uploaded = (new Factory())->createUploadedFile($stream, null);

        self::assertSame(strlen($payload), $uploaded->getSize());
    }

    public function testExplicitZeroSizeIsPreserved(): void
    {
        // Zero is a legitimate size value (an empty upload) and must NOT
        // trigger the null-derivation path.
        $stream = new Stream('non-empty-but-overridden', 'php://temp');
        $uploaded = (new Factory())->createUploadedFile($stream, 0);

        self::assertSame(0, $uploaded->getSize());
    }

    public function testExplicitSizeOverridesStreamReportedSize(): void
    {
        // The caller might report the wire size while the stream is the
        // already-decoded buffer. The factory must trust the caller's int.
        $stream = new Stream('decoded', 'php://temp');
        $uploaded = (new Factory())->createUploadedFile($stream, 9999);

        self::assertSame(9999, $uploaded->getSize());
    }

    public function testNullSizeOnIndeterminateStreamStaysNull(): void
    {
        // When the stream itself reports null (e.g. detached/closed) the
        // derivation falls through and the UploadedFile reports null.
        $stream = new Stream('seed', 'php://temp');
        $stream->close();

        $uploaded = (new Factory())->createUploadedFile($stream, null, \UPLOAD_ERR_NO_FILE);
        self::assertNull($uploaded->getSize());
    }
}
