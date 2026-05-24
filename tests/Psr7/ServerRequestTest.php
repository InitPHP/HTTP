<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Psr7;

use Http\Psr7Test\ServerRequestIntegrationTest;
use InitPHP\HTTP\Message\ServerRequest;
use InitPHP\HTTP\Message\Stream;
use InitPHP\HTTP\Message\Uri;
use Psr\Http\Message\StreamInterface;

final class ServerRequestTest extends ServerRequestIntegrationTest
{
    public function createSubject()
    {
        return (new ServerRequest('GET', '/', [], null, '1.1', $_SERVER))
            ->setCookieParams($_COOKIE);
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
