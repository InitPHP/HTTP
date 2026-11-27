<?php

declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Client\Retry;

use InitPHP\HTTP\Client\Retry\RetryAfter;
use InitPHP\HTTP\Message\Response;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage for the RFC 9110 `Retry-After` parser: delay-seconds form,
 * the three HTTP-date forms read as GMT, past dates clamped to zero, and
 * graceful null on anything else.
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

    /**
     * @return array<string, array{string}>
     */
    public static function invalidValues(): array
    {
        return [
            'negative'           => ['-5'],
            'negative zero'      => ['-0'],
            'plus sign'          => ['+5'],
            'fractional'         => ['1.5'],
            'exponent'           => ['1e3'],
            'relative phrase'    => ['tomorrow'],
            'now'                => ['now'],
            'relative offset'    => ['+1 hour'],
            'trailing words'     => ['120 seconds'],
            'iso 8601'           => ['2015-10-21T07:28:00Z'],
            'date with zone'     => ['Wed, 21 Oct 2015 07:28:00 +0300'],
            'nonexistent day'    => ['Tue, 31 Feb 2015 07:28:00 GMT'],
            'hour out of range'  => ['Wed, 21 Oct 2015 25:00:00 GMT'],
        ];
    }

    /**
     * @dataProvider invalidValues
     */
    public function testInvalidValuesReturnNull(string $value): void
    {
        self::assertNull(RetryAfter::parse($value, 1445412480));
    }

    public function testParsesRfc850Date(): void
    {
        $now = \strtotime('Sun, 06 Nov 1994 08:48:37 GMT');
        self::assertIsInt($now);

        $delay = RetryAfter::parse('Sunday, 06-Nov-94 08:49:37 GMT', $now);
        self::assertEqualsWithDelta(60.0, $delay, 1e-9);
    }

    public function testRfc850TwoDigitYearInTheFarFutureMeansThePast(): void
    {
        // 94 would be 2094 under a plain "20yy" reading, over 50 years ahead
        // of any current date, so it stands for 1994 and clamps to zero.
        self::assertSame(0.0, RetryAfter::parse('Sunday, 06-Nov-94 08:49:37 GMT'));
    }

    public function testParsesAsctimeDate(): void
    {
        $now = \strtotime('Sun, 06 Nov 1994 08:48:37 GMT');
        self::assertIsInt($now);

        self::assertEqualsWithDelta(60.0, RetryAfter::parse('Sun Nov  6 08:49:37 1994', $now), 1e-9);
        self::assertEqualsWithDelta(60.0, RetryAfter::parse('Sun Nov 06 08:49:37 1994', $now), 1e-9);
    }

    public function testAsctimeDateIsReadAsGmtNotInThePhpDefaultTimeZone(): void
    {
        $previous = \date_default_timezone_get();
        \date_default_timezone_set('Europe/Istanbul'); // UTC+3
        try {
            $now = \strtotime('Wed, 21 Oct 2015 07:28:00 GMT');
            self::assertIsInt($now);

            $delay = RetryAfter::parse('Wed Oct 21 07:29:00 2015', $now);
            self::assertEqualsWithDelta(60.0, $delay, 1e-9, 'asctime carries no zone and means GMT');
        } finally {
            \date_default_timezone_set($previous);
        }
    }

    public function testEveryHttpDateFormIsGmtUnderANonUtcDefaultTimeZone(): void
    {
        $previous = \date_default_timezone_get();
        \date_default_timezone_set('America/Los_Angeles');
        try {
            $now = \strtotime('Sun, 06 Nov 1994 08:48:37 GMT');
            self::assertIsInt($now);

            foreach ([
                'Sun, 06 Nov 1994 08:49:37 GMT',
                'Sunday, 06-Nov-94 08:49:37 GMT',
                'Sun Nov  6 08:49:37 1994',
            ] as $value) {
                self::assertEqualsWithDelta(60.0, RetryAfter::parse($value, $now), 1e-9, $value);
            }
        } finally {
            \date_default_timezone_set($previous);
        }
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
