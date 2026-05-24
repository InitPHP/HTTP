<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Psr7;

use Http\Psr7Test\RequestIntegrationTest;
use InitPHP\HTTP\Message\Request;
use InitPHP\HTTP\Message\Stream;
use InitPHP\HTTP\Message\Uri;
use Psr\Http\Message\StreamInterface;

final class RequestTest extends RequestIntegrationTest
{
    public function createSubject()
    {
        return new Request('GET', '/');
    }

    public function createStream($data)
    {
        if ($data instanceof StreamInterface) {
            return $data;
        }
        return new Stream($data, 'php://temp');
    }

    public function createUri($uri)
    {
        return new Uri($uri);
    }
}
