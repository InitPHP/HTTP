<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Client\Retry;

use InitPHP\HTTP\Client\Retry\RetryAfter;
use InitPHP\HTTP\Message\Response;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage for the RFC 7231 `Retry-After` parser: delay-seconds form,
 * HTTP-date form, past dates clamped to zero, and graceful null on garbage.
 */
final class RetryAfterTest extends TestCase
{
    public function testParsesDelaySeconds(): void
    {
        self::assertEqualsWithDelta(120.0, RetryAfter::parse('120'), 1e-9);
        self::assertEqualsWithDelta(0.0, RetryAfter::parse('0'), 1e-9);
    }

    public function testParsesHttpDateRelativeToNow(): void
    {
        $now = \strtotime('Wed, 21 Oct 2015 07:28:00 GMT');
        self::assertIsInt($now);

        $delay = RetryAfter::parse('Wed, 21 Oct 2015 07:30:00 GMT', $now);
        self::assertEqualsWithDelta(120.0, $delay, 1e-9, 'two minutes ahead of "now"');
    }

    public function testPastHttpDateClampsToZero(): void
    {
        $now = \strtotime('Wed, 21 Oct 2015 07:30:00 GMT');
        self::assertIsInt($now);

        $delay = RetryAfter::parse('Wed, 21 Oct 2015 07:28:00 GMT', $now);
        self::assertSame(0.0, $delay);
    }

    public function testGarbageReturnsNull(): void
    {
        self::assertNull(RetryAfter::parse('not-a-date'));
        self::assertNull(RetryAfter::parse(''));
        self::assertNull(RetryAfter::parse('  '));
    }

    public function testFromResponseReadsHeader(): void
    {
        $response = new Response(429, ['Retry-After' => '30']);
        self::assertEqualsWithDelta(30.0, RetryAfter::fromResponse($response), 1e-9);
    }

    public function testFromResponseReturnsNullWhenHeaderAbsent(): void
    {
        $response = new Response(503);
        self::assertNull(RetryAfter::fromResponse($response));
    }
}
