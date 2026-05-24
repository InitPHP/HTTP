<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Psr17;

use InitPHP\HTTP\Factory\Factory;
use Interop\Http\Factory\UriFactoryTestCase;

final class UriFactoryTest extends UriFactoryTestCase
{
    protected function createUriFactory()
    {
        return new Factory();
    }
}
