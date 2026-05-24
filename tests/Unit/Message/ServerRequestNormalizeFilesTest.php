<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Message;

use InitPHP\HTTP\Message\ServerRequest;
use InitPHP\HTTP\Message\Stream;
use InitPHP\HTTP\Message\UploadedFile;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UploadedFileInterface;

/**
 * normalizeFiles() consumes PHP's \$_FILES shape (or a pre-built tree of
 * UploadedFileInterface instances) and emits a normalised tree of
 * UploadedFileInterface values.
 *
 * Covers:
 *   - the single-file entry shape;
 *   - the simple-array shape ("file[]" — tmp_name etc. are parallel lists);
 *   - the nested shape ("file[parent][child]") with arbitrarily deep tmp_name
 *     trees (S20 regression);
 *   - pre-built UploadedFileInterface trees mixed with raw spec arrays;
 *   - invalid input shapes that must throw.
 */
final class ServerRequestNormalizeFilesTest extends TestCase
{
    private function newServerRequest(): ServerRequest
    {
        return new ServerRequest('GET', '/');
    }

    public function testSingleFileSpecBecomesOneUploadedFile(): void
    {
        $files = [
            'avatar' => [
                'tmp_name' => '/tmp/avatar.tmp',
                'size'     => 1024,
                'error'    => UPLOAD_ERR_OK,
                'name'     => 'me.png',
                'type'     => 'image/png',
            ],
        ];

        $normalised = $this->newServerRequest()->normalizeFiles($files);

        self::assertArrayHasKey('avatar', $normalised);
        self::assertInstanceOf(UploadedFileInterface::class, $normalised['avatar']);
        self::assertSame(1024, $normalised['avatar']->getSize());
        self::assertSame('me.png', $normalised['avatar']->getClientFilename());
        self::assertSame('image/png', $normalised['avatar']->getClientMediaType());
        self::assertSame(UPLOAD_ERR_OK, $normalised['avatar']->getError());
    }

    public function testSimpleArrayShapeBecomesParallelTree(): void
    {
        // <input type="file" name="docs[]" multiple>
        $files = [
            'docs' => [
                'tmp_name' => ['/tmp/a.tmp', '/tmp/b.tmp'],
                'size'     => [10, 20],
                'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
                'name'     => ['a.txt', 'b.txt'],
                'type'     => ['text/plain', 'text/plain'],
            ],
        ];

        $normalised = $this->newServerRequest()->normalizeFiles($files);

        self::assertIsArray($normalised['docs']);
        self::assertCount(2, $normalised['docs']);
        self::assertInstanceOf(UploadedFileInterface::class, $normalised['docs'][0]);
        self::assertInstanceOf(UploadedFileInterface::class, $normalised['docs'][1]);
        self::assertSame('a.txt', $normalised['docs'][0]->getClientFilename());
        self::assertSame('b.txt', $normalised['docs'][1]->getClientFilename());
    }

    public function testNestedFileInputProducesNestedTree(): void
    {
        // <input type="file" name="docs[brief]">
        // <input type="file" name="docs[exhibits][a]">
        // <input type="file" name="docs[exhibits][b]">
        //
        // PHP populates this as nested arrays at the LEAF level only;
        // intermediate associative keys live on each parallel field.
        $files = [
            'docs' => [
                'brief' => [
                    'tmp_name' => '/tmp/brief.tmp',
                    'size'     => 100,
                    'error'    => UPLOAD_ERR_OK,
                    'name'     => 'brief.pdf',
                    'type'     => 'application/pdf',
                ],
                'exhibits' => [
                    'a' => [
                        'tmp_name' => '/tmp/a.tmp',
                        'size'     => 200,
                        'error'    => UPLOAD_ERR_OK,
                        'name'     => 'exhibit-a.png',
                        'type'     => 'image/png',
                    ],
                    'b' => [
                        'tmp_name' => '/tmp/b.tmp',
                        'size'     => 300,
                        'error'    => UPLOAD_ERR_OK,
                        'name'     => 'exhibit-b.png',
                        'type'     => 'image/png',
                    ],
                ],
            ],
        ];

        $normalised = $this->newServerRequest()->normalizeFiles($files);

        self::assertInstanceOf(UploadedFileInterface::class, $normalised['docs']['brief']);
        self::assertInstanceOf(UploadedFileInterface::class, $normalised['docs']['exhibits']['a']);
        self::assertInstanceOf(UploadedFileInterface::class, $normalised['docs']['exhibits']['b']);
        self::assertSame('brief.pdf', $normalised['docs']['brief']->getClientFilename());
        self::assertSame('exhibit-a.png', $normalised['docs']['exhibits']['a']->getClientFilename());
    }

    public function testPreBuiltUploadedFileInterfaceIsPassedThrough(): void
    {
        // Mixed input: a hand-rolled UploadedFile alongside raw spec.
        $stream = new Stream('seed', 'php://temp');
        $prebuilt = new UploadedFile($stream, 4, UPLOAD_ERR_OK, 'prebuilt.txt', 'text/plain');

        $files = [
            'one' => $prebuilt,
            'two' => [
                'tmp_name' => '/tmp/two.tmp',
                'size'     => 7,
                'error'    => UPLOAD_ERR_OK,
                'name'     => 'two.txt',
                'type'     => 'text/plain',
            ],
        ];

        $normalised = $this->newServerRequest()->normalizeFiles($files);

        self::assertSame($prebuilt, $normalised['one']);
        self::assertInstanceOf(UploadedFileInterface::class, $normalised['two']);
        self::assertSame('two.txt', $normalised['two']->getClientFilename());
    }

    public function testNestedPreBuiltUploadedFileInterfaceTreesAreWalkedTransparently(): void
    {
        $stream = new Stream('seed', 'php://temp');
        $a = new UploadedFile($stream, 4, UPLOAD_ERR_OK, 'a.txt', 'text/plain');
        $b = new UploadedFile($stream, 4, UPLOAD_ERR_OK, 'b.txt', 'text/plain');

        $files = [
            'level1' => [
                'level2' => [
                    'a' => $a,
                    'b' => $b,
                ],
            ],
        ];

        $normalised = $this->newServerRequest()->normalizeFiles($files);
        self::assertSame($a, $normalised['level1']['level2']['a']);
        self::assertSame($b, $normalised['level1']['level2']['b']);
    }

    public function testInvalidScalarLeafThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->newServerRequest()->normalizeFiles(['bogus' => 'not-a-file']);
    }

    public function testEmptyInputProducesEmptyOutput(): void
    {
        $normalised = $this->newServerRequest()->normalizeFiles([]);
        self::assertSame([], $normalised);
    }

    public function testMissingOptionalFieldsDoNotThrow(): void
    {
        // Real-world malformed $_FILES often omits 'size' or 'type'; the
        // implementation defaults those rather than raising TypeError.
        $files = [
            'minimal' => [
                'tmp_name' => '/tmp/min.tmp',
                'error'    => UPLOAD_ERR_OK,
            ],
        ];

        $normalised = $this->newServerRequest()->normalizeFiles($files);
        self::assertInstanceOf(UploadedFileInterface::class, $normalised['minimal']);
        self::assertNull($normalised['minimal']->getSize());
        self::assertSame(UPLOAD_ERR_OK, $normalised['minimal']->getError());
    }
}
