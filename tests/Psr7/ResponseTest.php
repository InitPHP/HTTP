<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Psr7;

use Http\Psr7Test\ResponseIntegrationTest;
use InitPHP\HTTP\Message\Response;
use InitPHP\HTTP\Message\Stream;
use Psr\Http\Message\StreamInterface;

final class ResponseTest extends ResponseIntegrationTest
{
    public function createSubject()
    {
        return new Response();
    }

    public function createStream($data)
    {
        if ($data instanceof StreamInterface) {
            return $data;
        }
        return new Stream($data, 'php://temp');
    }
}
