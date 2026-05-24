<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Psr17;

use InitPHP\HTTP\Factory\Factory;
use Interop\Http\Factory\ResponseFactoryTestCase;

final class ResponseFactoryTest extends ResponseFactoryTestCase
{
    protected function createResponseFactory()
    {
        return new Factory();
    }
}
