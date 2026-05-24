<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Message;

use InitPHP\HTTP\Message\Request;
use InitPHP\HTTP\Message\Uri;
use PHPUnit\Framework\TestCase;

/**
 * Complements tests/Immutability/MessageImmutabilityTest with extra coverage
 * on PSR-7's Host header synchronisation contract.
 *
 * PSR-7 RequestInterface::withUri():
 *   - by default updates the Host header from the new URI;
 *   - when $preserveHost is true AND the request already has a Host header,
 *     the existing Host is preserved instead.
 *
 * The trait-level updateHostFromUri() also has a quirky ordering: it injects
 * the Host header at the front of the headers array so emitters serialise it
 * first, matching the canonical RFC 7230 layout.
 */
final class RequestImmutabilityTest extends TestCase
{
    public function testWithUriSyncsHostHeaderByDefault(): void
    {
        $request = new Request('GET', 'https://original.example/path');
        self::assertSame('original.example', $request->getHeaderLine('Host'));

        $updated = $request->withUri(new Uri('https://elsewhere.example/other'));
        self::assertSame('elsewhere.example', $updated->getHeaderLine('Host'));
    }

    public function testWithUriRespectsPreserveHostWhenHostHeaderExists(): void
    {
        $request = (new Request('GET', 'https://original.example/path'))
            ->withHeader('Host', 'pinned.example');

        $updated = $request->withUri(new Uri('https://elsewhere.example/other'), true);

        self::assertSame('pinned.example', $updated->getHeaderLine('Host'));
    }

    public function testPreserveHostSyncsAnywayWhenNoHostHeader(): void
    {
        // PSR-7 contract: preserveHost only protects an *existing* Host header.
        // If none was set, the URI's host is used regardless.
        $request = (new Request('GET', '/relative'))
            ->withoutHeader('Host');
        self::assertFalse($request->hasHeader('Host'));

        $updated = $request->withUri(new Uri('https://from-uri.example/x'), true);

        self::assertSame('from-uri.example', $updated->getHeaderLine('Host'));
    }

    public function testWithUriIncludesNonStandardPortInHost(): void
    {
        $request = new Request('GET', 'http://a.example/');
        $updated = $request->withUri(new Uri('http://b.example:8080/'));

        self::assertSame('b.example:8080', $updated->getHeaderLine('Host'));
    }

    public function testWithUriOmitsStandardPortInHost(): void
    {
        $request = new Request('GET', 'https://a.example/');
        $updated = $request->withUri(new Uri('https://b.example:443/'));

        self::assertSame('b.example', $updated->getHeaderLine('Host'));
    }

    public function testWithMethodDoesNotMutateUri(): void
    {
        $request = new Request('GET', 'https://example.com/path?q=1');
        $updated = $request->withMethod('POST');

        self::assertSame((string) $request->getUri(), (string) $updated->getUri());
        // And of course neither one shares a Uri instance after the deep clone.
        self::assertNotSame($request->getUri(), $updated->getUri());
    }

    public function testWithHeaderReturnsCloneAndPreservesOriginal(): void
    {
        $request = new Request('GET', 'https://example.com/');
        $clone = $request->withHeader('X-Trace', 'abc');

        self::assertNotSame($request, $clone);
        self::assertFalse($request->hasHeader('X-Trace'));
        self::assertSame('abc', $clone->getHeaderLine('X-Trace'));
    }

    public function testGetRequestTargetFallsBackToSlashWhenPathIsEmpty(): void
    {
        $request = new Request('GET', new Uri(''));
        self::assertSame('/', $request->getRequestTarget());
    }

    public function testGetRequestTargetIncludesQueryWhenPresent(): void
    {
        $request = new Request('GET', new Uri('/orders?status=open&limit=50'));
        self::assertSame('/orders?status=open&limit=50', $request->getRequestTarget());
    }

    public function testWithRequestTargetTakesPrecedenceOverUri(): void
    {
        $request = (new Request('GET', '/will-be-ignored'))
            ->withRequestTarget('*');

        self::assertSame('*', $request->getRequestTarget());
    }

    public function testWithRequestTargetRejectsWhitespace(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Request('GET', '/'))->withRequestTarget("/has space");
    }

    public function testIsMethodMatchesCaseInsensitively(): void
    {
        $request = new Request('post', 'https://example.com/');
        self::assertTrue($request->isPost());
        self::assertTrue($request->isMethod('POST', 'PUT'));
        self::assertFalse($request->isMethod('GET'));
    }
}
