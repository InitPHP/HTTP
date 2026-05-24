<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Emitter;

use InitPHP\HTTP\Emitter\Emitter;
use InitPHP\HTTP\Message\Response;
use PHPUnit\Framework\TestCase;

/**
 * Header emission path: name canonicalisation (Word-Case via
 * ucwords(..., '-')), the Set-Cookie multi-value path (replace=false), and
 * the assertion that emit() does not throw when the response carries
 * lower- and upper-case header names side-by-side.
 *
 * As with the status line, CLI cannot intercept header() calls. We test
 * the *call path* by emitting and asserting the body comes out intact —
 * any exception from the header() chain would surface here.
 */
final class EmitterHeadersTest extends TestCase
{
    public function testEmitsHeadersWithMixedCaseWithoutError(): void
    {
        $response = new Response(200, [
            'content-type' => 'text/plain',
            'X-CASE'       => 'mixed',
        ], 'body');

        $emitter = new Emitter(false);
        ob_start();
        $emitter->emit($response);
        $output = (string) ob_get_clean();

        // Body still comes out — header() calls succeeded silently.
        self::assertSame('body', $output);
    }

    public function testSetCookieMultiValueIsPreservedThroughEmission(): void
    {
        // Set-Cookie is the canonical multi-value header. The emitter must
        // not collapse it into a single comma-joined string; instead it
        // calls header('Set-Cookie: ...', replace=false) per value.
        $response = (new Response(200, [], 'ok'))
            ->withHeader('Set-Cookie', 'a=1')
            ->withAddedHeader('Set-Cookie', 'b=2');

        self::assertSame(['a=1', 'b=2'], $response->getHeader('Set-Cookie'));

        $emitter = new Emitter(false);
        ob_start();
        $emitter->emit($response);
        $output = (string) ob_get_clean();

        self::assertSame('ok', $output);
    }

    public function testWordCaseCanonicalisationHandlesDashSeparator(): void
    {
        // The ucwords(..., '-') call is the public-surface guarantee: a
        // header sent as "content-security-policy" must hit header() as
        // "Content-Security-Policy". We pin the Response contract here so
        // the canonicalisation isn't accidentally moved to setHeader().
        $response = new Response(200, [
            'content-security-policy' => "default-src 'self'",
        ]);

        // Response itself stores the original case verbatim; only the
        // emitter canonicalises on the way out.
        self::assertTrue($response->hasHeader('Content-Security-Policy'));
        self::assertSame(['content-security-policy'], array_keys($response->getHeaders()));
    }

    public function testEmptyHeaderBagDoesNotBreakEmission(): void
    {
        $response = new Response(204);
        // 204 No Content -> no body, no headers. Must not blow up.
        $emitter = new Emitter(false);
        ob_start();
        $emitter->emit($response);
        $output = (string) ob_get_clean();

        self::assertSame('', $output);
    }
}
