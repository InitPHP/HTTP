<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Factory;

use InitPHP\HTTP\Factory\Factory;
use InitPHP\HTTP\Message\Stream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

/**
 * createStreamFromResource() short-circuit behaviour. Per the docblock the
 * factory accepts either a PHP resource OR a StreamInterface; when given a
 * StreamInterface it returns *the same instance* rather than wrapping it
 * again. That identity guarantee matters for callers that store a stream
 * reference and expect it to remain mutation-visible through the factory.
 */
final class FactoryStreamFromResourceTest extends TestCase
{
    public function testReturnsSameInstanceWhenGivenStreamInterface(): void
    {
        $existing = new Stream('seed', null);
        $returned = (new Factory())->createStreamFromResource($existing);

        self::assertSame($existing, $returned, 'Factory must short-circuit on StreamInterface');
    }

    public function testWrapsRawResource(): void
    {
        $handle = fopen('php://memory', 'r+b');
        self::assertIsResource($handle);
        fwrite($handle, 'resource-body');
        fseek($handle, 0);

        $stream = (new Factory())->createStreamFromResource($handle);
        self::assertInstanceOf(StreamInterface::class, $stream);
        self::assertSame('resource-body', (string) $stream);
    }
}
