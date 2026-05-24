<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Factory;

use InitPHP\HTTP\Factory\Factory;
use PHPUnit\Framework\TestCase;

/**
 * createStreamFromFile() input validation. The PSR-17 spec demands:
 *
 *   - empty filename                -> RuntimeException
 *   - mode whose first char is not  -> InvalidArgumentException
 *     one of [r,w,a,x,c]
 *   - filename that fopen() cannot  -> RuntimeException
 *     open (typically ENOENT)
 *   - happy path returns a stream   -> contents readable verbatim
 *     wrapping the file handle
 */
final class FactoryStreamFromFileTest extends TestCase
{
    public function testEmptyFilenameThrowsRuntimeException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Path cannot be empty');
        (new Factory())->createStreamFromFile('');
    }

    public function testEmptyModeThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Factory())->createStreamFromFile(__FILE__, '');
    }

    public function testInvalidModeFirstCharacterThrowsInvalidArgumentException(): void
    {
        // The first character must be one of r/w/a/x/c. 'z' triggers the
        // explicit InvalidArgumentException before fopen() ever runs, so
        // the test is independent of platform fopen() leniency.
        $this->expectException(\InvalidArgumentException::class);
        (new Factory())->createStreamFromFile(__FILE__, 'z');
    }

    public function testNonexistentFileThrowsRuntimeException(): void
    {
        $path = sys_get_temp_dir() . '/does-not-exist-' . uniqid('', true);
        $this->expectException(\RuntimeException::class);
        (new Factory())->createStreamFromFile($path, 'r');
    }

    public function testHappyPathReadsFileContents(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'init-factory-');
        self::assertIsString($tmp);
        file_put_contents($tmp, 'factory-body');

        try {
            $stream = (new Factory())->createStreamFromFile($tmp, 'r');
            self::assertSame('factory-body', (string) $stream);
        } finally {
            @unlink($tmp);
        }
    }

    public function testValidModesAreAcceptedForExistingFile(): void
    {
        // The PSR-17 spec accepts the full fopen() mode vocabulary as long
        // as the first character is r/w/a/x/c. We pin a representative set
        // here so regressions in the guard surface immediately.
        $tmp = tempnam(sys_get_temp_dir(), 'init-factory-');
        self::assertIsString($tmp);
        file_put_contents($tmp, 'x');

        try {
            foreach (['r', 'rb', 'r+', 'w', 'wb', 'a', 'a+', 'c', 'c+', 'x'] as $mode) {
                // 'x' would fail on an existing file; recreate per iteration.
                if ($mode[0] === 'x') {
                    @unlink($tmp);
                }
                $stream = (new Factory())->createStreamFromFile($tmp, $mode);
                self::assertNotNull($stream, 'mode '.$mode.' must yield a stream');
                if ($mode[0] === 'x') {
                    // Re-populate for subsequent iterations.
                    file_put_contents($tmp, 'x');
                }
            }
        } finally {
            @unlink($tmp);
        }
    }
}
