<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Immutability;

use InitPHP\HTTP\Message\Request;
use InitPHP\HTTP\Message\Response;
use InitPHP\HTTP\Message\ServerRequest;
use InitPHP\HTTP\Message\Stream;
use InitPHP\HTTP\Message\Uri;
use PHPUnit\Framework\TestCase;

/**
 * PSR-7 immutability sözleşmesini sertçe denetler.
 *
 * Bu testler eklenmeden önce __clone tanımsız olduğu için Stream ve URI
 * reference'ları clone'lar arası paylaşılıyordu; "with" mutator'undan dönen
 * yeni instance üzerinde body'ye yazmak orijinali bozuyordu.
 */
final class MessageImmutabilityTest extends TestCase
{
    public function testWithHeaderDoesNotShareBodyStream(): void
    {
        $request = new Request('GET', '/', [], new Stream('original', 'php://temp'));
        $clone   = $request->withHeader('X-Test', 'yes');

        $clone->getBody()->write(' tampered');

        self::assertSame('original', (string) $request->getBody(), 'withHeader cloned body must not share state with the original');
        self::assertStringContainsString('tampered', (string) $clone->getBody());
    }

    public function testWithMethodDoesNotShareBodyStream(): void
    {
        $request = new Request('GET', '/', [], new Stream('keep', 'php://temp'));
        $clone   = $request->withMethod('POST');

        $clone->getBody()->write(' modified');

        self::assertSame('keep', (string) $request->getBody());
        self::assertNotSame('keep', (string) $clone->getBody());
    }

    public function testWithUriDoesNotMutateOriginal(): void
    {
        $request = new Request('GET', 'https://a.example/foo');
        $clone   = $request->withHeader('X-Test', 'yes');

        // PSR-7 ihlali: clone'un URI'sini değiştirmek orijinali değiştirmemeli
        $clone->getUri()->setHost('attacker.example');

        self::assertSame('a.example', $request->getUri()->getHost());
        self::assertSame('attacker.example', $clone->getUri()->getHost());
    }

    public function testResponseWithStatusDoesNotShareBodyStream(): void
    {
        // PSR-7 Stream::write current position'a yazar (overwrite, append değil).
        // Burada doğrulanan tek şey: clone'a yazmak orijinal body'i bozmuyor.
        $response = new Response(200, [], new Stream('hello', 'php://temp'));
        $clone    = $response->withStatus(404);

        $clone->getBody()->write('XXXXX');

        self::assertSame('hello', (string) $response->getBody());
        self::assertNotSame('hello', (string) $clone->getBody());
    }

    public function testWithHeaderReturnsNewInstance(): void
    {
        $request = new Request('GET', '/');
        $clone   = $request->withHeader('X-Test', 'yes');

        self::assertNotSame($request, $clone);
        self::assertFalse($request->hasHeader('X-Test'));
        self::assertTrue($clone->hasHeader('X-Test'));
    }

    public function testWithoutHeaderActuallyRemovesHeader(): void
    {
        // outHeader/withoutHeader bug regresyon koruması
        $request = (new Request('GET', '/'))->withHeader('Content-Type', 'text/plain');
        $clone   = $request->withoutHeader('Content-Type');

        self::assertTrue($request->hasHeader('Content-Type'));
        self::assertFalse($clone->hasHeader('Content-Type'));
        self::assertSame([], $clone->getHeader('Content-Type'));
        self::assertArrayNotHasKey('Content-Type', $clone->getHeaders());
    }

    public function testUriCloneIndependence(): void
    {
        $uri   = new Uri('https://example.com:8443/foo');
        $clone = clone $uri;
        $clone->setHost('other.example');
        $clone->setPort(9000);

        self::assertSame('example.com', $uri->getHost());
        self::assertSame(8443, $uri->getPort());
        self::assertSame('other.example', $clone->getHost());
        self::assertSame(9000, $clone->getPort());
    }

    public function testServerRequestWithAttributeIsImmutable(): void
    {
        $request = new ServerRequest('GET', '/');
        $clone   = $request->withAttribute('user_id', 42);

        self::assertNull($request->getAttribute('user_id'));
        self::assertSame(42, $clone->getAttribute('user_id'));
    }

    public function testStreamCloneCopiesContentNotResource(): void
    {
        $original = new Stream('seed', 'php://temp');
        $cloned   = clone $original;

        // İkisi de aynı içeriği görüyor mu? (kopya garantisi)
        self::assertSame('seed', (string) $original);
        self::assertSame('seed', (string) $cloned);

        $cloned->write(' extra');
        self::assertSame('seed', (string) $original, 'Stream clone must not share the underlying resource');
        self::assertStringContainsString('extra', (string) $cloned);
    }

    public function testStringBackendStreamCloneIndependence(): void
    {
        $original = new Stream('hello', null);
        $cloned   = clone $original;
        $cloned->write(' world');

        self::assertSame('hello', (string) $original);
        self::assertNotSame('hello', (string) $cloned);
    }
}
