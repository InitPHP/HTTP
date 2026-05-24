<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Psr17;

use InitPHP\HTTP\Factory\Factory;
use InitPHP\HTTP\Message\Uri;
use Interop\Http\Factory\ServerRequestFactoryTestCase;

final class ServerRequestFactoryTest extends ServerRequestFactoryTestCase
{
    protected function createServerRequestFactory()
    {
        return new Factory();
    }

    protected function createUri($uri)
    {
        return new Uri($uri);
    }
}
