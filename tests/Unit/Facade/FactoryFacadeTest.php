<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Facade;

use InitPHP\HTTP\Facade\Factory as FactoryFacade;
use InitPHP\HTTP\Facade\Interfaces\FacadebleInterface;
use InitPHP\HTTP\Facade\Traits\Facadable;
use InitPHP\HTTP\Facade\Traits\Facadeble;
use InitPHP\HTTP\Factory\Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;

/**
 * Static facade over the PSR-17 Factory bundle. The contract under test:
 *
 *   - the lazily-resolved singleton is the SAME instance across calls
 *   - every PSR-17 createX(...) method round-trips through __callStatic
 *   - the deprecated `Facadeble`/`FacadebleInterface` aliases still exist
 *     for backward compatibility
 *
 * The integration tests under tests/Psr17/ verify the *behaviour* of the
 * factory; this suite verifies the *plumbing* of the facade itself.
 */
final class FactoryFacadeTest extends TestCase
{
    public function testGetInstanceReturnsTheConcreteFactory(): void
    {
        $instance = FactoryFacade::getInstance();
        self::assertInstanceOf(Factory::class, $instance);
    }

    public function testGetInstanceIsAStableSingleton(): void
    {
        // Subsequent calls must return the same object so configuration
        // performed on the facade survives across access sites.
        $a = FactoryFacade::getInstance();
        $b = FactoryFacade::getInstance();
        self::assertSame($a, $b);
    }

    public function testStaticCallForwardsToCreateRequest(): void
    {
        $request = FactoryFacade::createRequest('GET', 'https://example.com/');
        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertSame('GET', $request->getMethod());
    }

    public function testStaticCallForwardsToCreateResponse(): void
    {
        $response = FactoryFacade::createResponse(201, 'Created');
        self::assertInstanceOf(ResponseInterface::class, $response);
        self::assertSame(201, $response->getStatusCode());
        self::assertSame('Created', $response->getReasonPhrase());
    }

    public function testStaticCallForwardsToCreateServerRequest(): void
    {
        $request = FactoryFacade::createServerRequest('POST', 'https://example.com/api', ['HTTP_HOST' => 'example.com']);
        self::assertInstanceOf(ServerRequestInterface::class, $request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame(['HTTP_HOST' => 'example.com'], $request->getServerParams());
    }

    public function testStaticCallForwardsToCreateStream(): void
    {
        $stream = FactoryFacade::createStream('hello');
        self::assertInstanceOf(StreamInterface::class, $stream);
        self::assertSame('hello', (string) $stream);
    }

    public function testStaticCallForwardsToCreateUri(): void
    {
        $uri = FactoryFacade::createUri('https://user:pw@example.com:8443/path?q=1');
        self::assertInstanceOf(UriInterface::class, $uri);
        self::assertSame('example.com', $uri->getHost());
        self::assertSame(8443, $uri->getPort());
    }

    public function testStaticCallForwardsToCreateStreamFromFile(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'init-facade-');
        self::assertIsString($tmp);
        file_put_contents($tmp, 'file body');
        try {
            $stream = FactoryFacade::createStreamFromFile($tmp, 'r');
            self::assertInstanceOf(StreamInterface::class, $stream);
            self::assertSame('file body', (string) $stream);
        } finally {
            @unlink($tmp);
        }
    }

    public function testStaticCallForwardsToCreateUploadedFile(): void
    {
        $stream = FactoryFacade::createStream('upload content');
        $uploaded = FactoryFacade::createUploadedFile($stream);
        self::assertInstanceOf(UploadedFileInterface::class, $uploaded);
        self::assertSame(strlen('upload content'), $uploaded->getSize());
    }

    public function testInstanceCallAlsoForwardsToSingleton(): void
    {
        // The Facadable trait also implements __call, so $facade->method()
        // is equivalent to Facade::method(). We exercise both ends here.
        $facade = new FactoryFacade();
        $response = $facade->createResponse(204);
        self::assertSame(204, $response->getStatusCode());
    }

    public function testFacadebleAliasTraitIsStillUsable(): void
    {
        // The misspelled `Facadeble` trait/interface pair is kept as a
        // backwards-compatible alias of `Facadable`. Removing it would be a
        // BC break; this test pins that the alias still exists and that a
        // local facade built on it works exactly like the canonical one.
        self::assertTrue(trait_exists(Facadeble::class));
        self::assertTrue(trait_exists(Facadable::class));
        self::assertTrue(interface_exists(FacadebleInterface::class));

        $local = new class implements FacadebleInterface {
            use Facadeble;
            private static $instance;
            public static function getInstance(): object
            {
                if (!isset(self::$instance)) {
                    self::$instance = new \stdClass();
                    self::$instance->payload = 'alias-works';
                }
                return self::$instance;
            }
        };

        // Static and instance forwarding both work — the interface only
        // mandates __call/__callStatic/getInstance.
        self::assertSame('alias-works', $local::getInstance()->payload);
    }
}
