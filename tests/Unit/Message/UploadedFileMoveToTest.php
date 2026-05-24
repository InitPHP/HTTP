<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Message;

use InitPHP\HTTP\Message\Stream;
use InitPHP\HTTP\Message\UploadedFile;
use PHPUnit\Framework\TestCase;

/**
 * UploadedFile::moveTo() exercises two distinct paths depending on whether
 * the file was constructed from a path (cli rename / move_uploaded_file)
 * or from a StreamInterface (chunked copy via Stream::read/write).
 *
 * These tests focus on:
 *   - the CLI rename path (the package selects rename() under PHP_SAPI === 'cli'),
 *   - the stream-copy path with payloads bigger than the 1 MiB read buffer,
 *   - the post-move invariants (subsequent moveTo / getStream throw),
 *   - the upload-error guards.
 */
final class UploadedFileMoveToTest extends TestCase
{
    /** @var list<string> */
    private $tempPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->tempPaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $this->tempPaths = [];
    }

    private function tempPath(string $hint = 'upload'): string
    {
        $path = sys_get_temp_dir() . '/initphp-http-' . $hint . '-' . bin2hex(random_bytes(6));
        $this->tempPaths[] = $path;
        return $path;
    }

    public function testCliRenamePathMovesFile(): void
    {
        $source = $this->tempPath('src');
        file_put_contents($source, 'cli-payload');

        $dest = $this->tempPath('dest');

        $file = new UploadedFile($source, filesize($source), UPLOAD_ERR_OK, 'source.txt', 'text/plain');
        $file->moveTo($dest);

        self::assertFileExists($dest);
        self::assertSame('cli-payload', file_get_contents($dest));
    }

    public function testStreamCopyPathHandlesPayloadLargerThanBuffer(): void
    {
        // 1 MiB read buffer in moveTo(); use 2.5 MiB so we exercise multiple
        // read/write iterations and verify nothing gets lost between chunks.
        $payload = str_repeat('A', 2_621_440); // 2.5 MiB

        $stream = new Stream($payload, 'php://temp');
        $file = new UploadedFile($stream, strlen($payload), UPLOAD_ERR_OK, 'big.bin', 'application/octet-stream');

        $dest = $this->tempPath('big');
        $file->moveTo($dest);

        self::assertFileExists($dest);
        self::assertSame(strlen($payload), filesize($dest));
        self::assertSame(hash('sha256', $payload), hash_file('sha256', $dest));
    }

    public function testSecondMoveAfterMoveThrows(): void
    {
        $source = $this->tempPath('once-only');
        file_put_contents($source, 'data');

        $file = new UploadedFile($source, 4, UPLOAD_ERR_OK);
        $file->moveTo($this->tempPath('first'));

        $this->expectException(\RuntimeException::class);
        $file->moveTo($this->tempPath('second'));
    }

    public function testGetStreamAfterMoveThrows(): void
    {
        $source = $this->tempPath('after-move');
        file_put_contents($source, 'data');

        $file = new UploadedFile($source, 4, UPLOAD_ERR_OK);
        $file->moveTo($this->tempPath('done'));

        $this->expectException(\RuntimeException::class);
        $file->getStream();
    }

    public function testMoveToRejectsEmptyTarget(): void
    {
        $source = $this->tempPath('reject');
        file_put_contents($source, 'data');
        $file = new UploadedFile($source, 4, UPLOAD_ERR_OK);

        $this->expectException(\InvalidArgumentException::class);
        $file->moveTo('');
    }

    public function testMoveToThrowsWhenSourceHadUploadError(): void
    {
        // When the upload errored, the constructor doesn't bind a source —
        // moveTo() must refuse cleanly rather than try to rename nothing.
        $file = new UploadedFile('/nope', null, UPLOAD_ERR_INI_SIZE);

        $this->expectException(\RuntimeException::class);
        $file->moveTo($this->tempPath('never-reached'));
    }

    public function testGetStreamThrowsWhenUploadHadError(): void
    {
        $file = new UploadedFile('/nope', null, UPLOAD_ERR_NO_FILE);

        $this->expectException(\RuntimeException::class);
        $file->getStream();
    }
}
