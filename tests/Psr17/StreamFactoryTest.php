<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Psr17;

use InitPHP\HTTP\Factory\Factory;
use Interop\Http\Factory\StreamFactoryTestCase;

final class StreamFactoryTest extends StreamFactoryTestCase
{
    protected function createStreamFactory()
    {
        return new Factory();
    }
}
