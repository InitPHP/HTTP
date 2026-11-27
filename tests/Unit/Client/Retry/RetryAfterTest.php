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
            'wrong weekday (IMF-fixdate)' => ['Mon, 21 Oct 2015 07:28:00 GMT'],
            'wrong weekday (RFC 850)'     => ['Monday, 21-Oct-15 07:28:00 GMT'],
            'wrong weekday (asctime)'     => ['Mon Oct 21 07:28:00 2015'],
            'wrong weekday, one day off'  => ['Tue, 21 Oct 2015 07:28:00 GMT'],
            'full name in IMF-fixdate'    => ['Wednesday, 21 Oct 2015 07:28:00 GMT'],
            'abbreviated name in RFC 850' => ['Wed, 21-Oct-15 07:28:00 GMT'],
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

    public function testRfc850TwoDigitYearBeyondFiftyYearsMeansThePast(): void
    {
        // With "now" in 2015, 94 would be 2094 (over 50 years ahead), so it
        // stands for 1994 and the delay clamps to zero. A plain "20yy" reading
        // would give a 79-year wait.
        $now = \strtotime('Wed, 21 Oct 2015 07:28:00 GMT');
        self::assertIsInt($now);

        self::assertSame(0.0, RetryAfter::parse('Sunday, 06-Nov-94 08:49:37 GMT', $now));
    }

    public function testRfc850TwoDigitYearWithinFiftyYearsMeansTheFuture(): void
    {
        // PHP's `y` token pivots at 1970 and would read 75 as 1975 (the past).
        // RFC 9110 puts 2075 within 50 years of a 2030 "now", so it is a date
        // 45 years ahead.
        $now = \strtotime('Tue, 01 Jan 2030 00:00:00 GMT');
        self::assertIsInt($now);

        $delay = RetryAfter::parse('Tuesday, 01-Jan-75 00:00:00 GMT', $now);
        self::assertNotNull($delay);
        self::assertGreaterThan(0.0, $delay);
        self::assertEqualsWithDelta(\strtotime('2075-01-01 00:00:00 GMT') - $now, $delay, 1e-9);
    }

    public function testRfc850TwoDigitYearJustInsideAndOutsideTheFiftyYearWindow(): void
    {
        $now = \strtotime('Thu, 01 Jan 2026 00:00:00 GMT');
        self::assertIsInt($now);

        // 2076-01-01 is exactly 50 years ahead: still the future.
        self::assertGreaterThan(0.0, RetryAfter::parse('Wednesday, 01-Jan-76 00:00:00 GMT', $now));
        // 2077-01-01 is past the window: read as 1977, so zero.
        self::assertSame(0.0, RetryAfter::parse('Saturday, 01-Jan-77 00:00:00 GMT', $now));
    }

    public function testWeekdayNameIsValidatedNotUsedToShiftTheDate(): void
    {
        $now = \strtotime('Sun, 06 Nov 1994 08:49:37 GMT');
        self::assertIsInt($now);

        self::assertSame(0.0, RetryAfter::parse('Sun, 06 Nov 1994 08:49:37 GMT', $now));
        self::assertNull(RetryAfter::parse('Mon, 06 Nov 1994 08:49:37 GMT', $now));
        self::assertNull(RetryAfter::parse('Monday, 06-Nov-94 08:49:37 GMT', $now));
        self::assertNull(RetryAfter::parse('Mon Nov  6 08:49:37 1994', $now));
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
