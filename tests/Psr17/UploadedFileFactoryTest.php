<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Psr17;

use InitPHP\HTTP\Factory\Factory;
use InitPHP\HTTP\Message\Stream;
use Interop\Http\Factory\UploadedFileFactoryTestCase;

final class UploadedFileFactoryTest extends UploadedFileFactoryTestCase
{
    protected function createUploadedFileFactory()
    {
        return new Factory();
    }

    protected function createStream($content)
    {
        return new Stream($content, 'php://temp');
    }
}
