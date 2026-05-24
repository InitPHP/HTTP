<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Message;

use InitPHP\HTTP\Message\Response;
use InitPHP\HTTP\Message\Uri;
use PHPUnit\Framework\TestCase;

/**
 * Response::redirect() convenience producer. Contract:
 *
 *   - Location header is ALWAYS set (regression: previously skipped when
 *     $second > 0 because only Refresh was set in that branch)
 *   - Refresh header is added only when $second > 0
 *   - Status defaults to 302 but is caller-controlled
 *   - $uri may be a string or a UriInterface; anything else throws
 *   - Returns a clone (immutability)
 */
final class ResponseRedirectTest extends TestCase
{
    public function testLocationIsAlwaysSetWithoutRefresh(): void
    {
        $response = (new Response())->redirect('https://example.com/dest');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('https://example.com/dest', $response->getHeaderLine('Location'));
        self::assertFalse($response->hasHeader('Refresh'));
    }

    public function testRefreshIsAddedAlongsideLocationWhenSecondIsPositive(): void
    {
        // Regression: previously only Refresh was set when $second > 0
        // which dropped non-browser clients. The fix makes Location
        // unconditional and Refresh additive.
        $response = (new Response())->redirect('https://example.com/dest', 301, 5);

        self::assertSame(301, $response->getStatusCode());
        self::assertSame('https://example.com/dest', $response->getHeaderLine('Location'));
        self::assertSame('5; url=https://example.com/dest', $response->getHeaderLine('Refresh'));
    }

    public function testRefreshIsNotAddedWhenSecondIsZero(): void
    {
        $response = (new Response())->redirect('/relative', 303, 0);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/relative', $response->getHeaderLine('Location'));
        self::assertFalse($response->hasHeader('Refresh'));
    }

    public function testNegativeSecondDoesNotAddRefresh(): void
    {
        $response = (new Response())->redirect('/relative', 302, -10);
        self::assertFalse($response->hasHeader('Refresh'));
    }

    public function testAcceptsUriInterfaceInstance(): void
    {
        $uri = new Uri('https://example.com/from-uri-instance?q=1');
        $response = (new Response())->redirect($uri);

        self::assertSame('https://example.com/from-uri-instance?q=1', $response->getHeaderLine('Location'));
    }

    public function testRejectsInvalidUriType(): void
    {
        // The redirect path explicitly rejects non-string, non-UriInterface
        // arguments; the @param docblock advertises string|UriInterface.
        $this->expectException(\InvalidArgumentException::class);
        /** @phpstan-ignore-next-line — intentional contract violation */
        (new Response())->redirect(12345);
    }

    public function testRedirectReturnsClone(): void
    {
        $original = new Response(200, ['X-Trace' => 'a']);
        $clone = $original->redirect('https://elsewhere.example/');

        self::assertNotSame($original, $clone);
        self::assertSame(200, $original->getStatusCode());
        self::assertFalse($original->hasHeader('Location'));
        self::assertSame(302, $clone->getStatusCode());
    }

    public function testStatusCodeOutsideValidRangeThrows(): void
    {
        // The clone path calls setStatusCode(), which enforces 100..599.
        $this->expectException(\InvalidArgumentException::class);
        (new Response())->redirect('/ok', 600);
    }
}
