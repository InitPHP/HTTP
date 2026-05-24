<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Psr7;

use Http\Psr7Test\UploadedFileIntegrationTest;
use InitPHP\HTTP\Message\Stream;
use InitPHP\HTTP\Message\UploadedFile;
use Psr\Http\Message\StreamInterface;

final class UploadedFileTest extends UploadedFileIntegrationTest
{
    public function createSubject()
    {
        return new UploadedFile(new Stream('content', 'php://temp'), 7, \UPLOAD_ERR_OK, 'test.txt', 'text/plain');
    }

    public function createStream($data)
    {
        if ($data instanceof StreamInterface) {
            return $data;
        }
        return new Stream($data, 'php://temp');
    }
}
