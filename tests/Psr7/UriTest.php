<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Psr7;

use Http\Psr7Test\UriIntegrationTest;
use InitPHP\HTTP\Message\Uri;

final class UriTest extends UriIntegrationTest
{
    public function createUri($uri)
    {
        return new Uri($uri);
    }
}
