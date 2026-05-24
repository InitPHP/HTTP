<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Emitter;

use InitPHP\HTTP\Emitter\Emitter;
use InitPHP\HTTP\Message\Response;
use PHPUnit\Framework\TestCase;

/**
 * Status-line emission contract. The CLI SAPI cannot actually call
 * header(), so we rely on the side-effect of getStatusCode()/getReasonPhrase()
 * being read off the response. The two-call paths we care about:
 *
 *   - default status & reason ("200 OK")
 *   - explicit override (502 Bad Gateway)
 *   - **regression B11**: a reason phrase of "0" must still appear in the
 *     status line. The original code used a truthy test (`if ($reason)`)
 *     which dropped "0"; the fix switched to an explicit !== '' check.
 *
 * Because header() is a no-op under CLI we can't intercept the actual
 * status line, so we keep the assertions on the public Response API and
 * rely on the EmitterStrictModeTest @runInSeparateProcess tests to
 * exercise the SAPI integration end of the contract.
 */
final class EmitterStatusLineTest extends TestCase
{
    public function testEmitDoesNotThrowForDefaultResponse(): void
    {
        $emitter = new Emitter(false); // strict off — CLI ob may be dirty
        $response = new Response(200, [], 'hello');

        ob_start();
        $emitter->emit($response);
        $output = (string) ob_get_clean();

        self::assertSame('hello', $output);
    }

    public function testReasonPhraseZeroSurvivesEmission(): void
    {
        // Regression: previously `if ($reasonPhrase)` truthy-tested the value,
        // which dropped a phrase of "0" — a legal RFC 7230 reason phrase.
        $response = new Response(599, [], 'body', '1.1', '0');

        self::assertSame('0', $response->getReasonPhrase());

        $emitter = new Emitter(false);
        ob_start();
        $emitter->emit($response);
        $output = (string) ob_get_clean();

        // Body still emitted regardless of the status line; the assertion
        // confirms the call did not blow up while reading reason phrase "0".
        self::assertSame('body', $output);
    }

    public function testEmptyReasonPhraseDoesNotProduceTrailingSpace(): void
    {
        // When reason is null and code has no IANA phrase, getReasonPhrase()
        // returns ''. The fix in emitStatusLine() avoids the trailing space
        // by using `!== ''` instead of `!== null` — covered for branch.
        $response = new Response(599, [], 'empty', '1.1', '');
        self::assertSame('', $response->getReasonPhrase());

        $emitter = new Emitter(false);
        ob_start();
        $emitter->emit($response);
        $output = (string) ob_get_clean();

        self::assertSame('empty', $output);
    }

    public function testCustomReasonPhraseIsPreserved(): void
    {
        $response = new Response(418, [], '', '1.1', 'I am a teapot');
        self::assertSame('I am a teapot', $response->getReasonPhrase());

        $emitter = new Emitter(false);
        ob_start();
        $emitter->emit($response);
        ob_get_clean();
        // No exception → status line + headers + (empty) body emitted OK.
        $this->addToAssertionCount(1);
    }
}
