<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Psr17;

use InitPHP\HTTP\Factory\Factory;
use InitPHP\HTTP\Message\Uri;
use Interop\Http\Factory\RequestFactoryTestCase;

final class RequestFactoryTest extends RequestFactoryTestCase
{
    protected function createRequestFactory()
    {
        return new Factory();
    }

    protected function createUri($uri)
    {
        return new Uri($uri);
    }
}
