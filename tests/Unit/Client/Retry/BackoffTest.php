<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Client\Retry;

use InitPHP\HTTP\Client\Retry\Backoff;
use InitPHP\HTTP\Client\Retry\RetryPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage for the Backoff calculator: the deterministic geometric base
 * series, the maxDelay cap, and the equal-jitter envelope bounds.
 */
final class BackoffTest extends TestCase
{
    public function testBaseDelayGrowsGeometrically(): void
    {
        $backoff = new Backoff(new RetryPolicy(5, 0.1, 2.0, 100.0, 0.0));

        self::assertEqualsWithDelta(0.1, $backoff->baseDelayFor(1), 1e-9);
        self::assertEqualsWithDelta(0.2, $backoff->baseDelayFor(2), 1e-9);
        self::assertEqualsWithDelta(0.4, $backoff->baseDelayFor(3), 1e-9);
        self::assertEqualsWithDelta(0.8, $backoff->baseDelayFor(4), 1e-9);
    }

    public function testBaseDelayIsCappedAtMaxDelay(): void
    {
        $backoff = new Backoff(new RetryPolicy(10, 1.0, 10.0, 5.0, 0.0));

        self::assertEqualsWithDelta(1.0, $backoff->baseDelayFor(1), 1e-9);
        self::assertEqualsWithDelta(5.0, $backoff->baseDelayFor(2), 1e-9, 'capped from 10 to 5');
        self::assertEqualsWithDelta(5.0, $backoff->baseDelayFor(5), 1e-9, 'stays capped');
    }

    public function testAttemptBelowOneIsTreatedAsOne(): void
    {
        $backoff = new Backoff(new RetryPolicy(3, 0.25, 2.0, 30.0, 0.0));

        self::assertEqualsWithDelta(0.25, $backoff->baseDelayFor(0), 1e-9);
        self::assertEqualsWithDelta(0.25, $backoff->baseDelayFor(-3), 1e-9);
    }

    public function testJitterDisabledReturnsExactBase(): void
    {
        $backoff = new Backoff(new RetryPolicy(3, 0.5, 2.0, 30.0, 0.0));

        self::assertEqualsWithDelta(0.5, $backoff->delayFor(1), 1e-9);
        self::assertEqualsWithDelta(1.0, $backoff->delayFor(2), 1e-9);
    }

    public function testJitterStaysWithinEqualJitterEnvelope(): void
    {
        $policy = new RetryPolicy(5, 1.0, 2.0, 100.0, 0.5);

        // randomizer = 0.0 collapses to the floor; 1.0 (clamped just under) to base.
        $floorBackoff = new Backoff($policy, static fn (): float => 0.0);
        $ceilBackoff  = new Backoff($policy, static fn (): float => 0.999999);

        // attempt 2 => base 2.0; envelope is [1.0, 2.0].
        self::assertEqualsWithDelta(1.0, $floorBackoff->delayFor(2), 1e-9);
        self::assertLessThanOrEqual(2.0, $ceilBackoff->delayFor(2));
        self::assertGreaterThanOrEqual(1.0, $ceilBackoff->delayFor(2));
    }

    public function testMisbehavingRandomizerIsClampedIntoEnvelope(): void
    {
        $policy = new RetryPolicy(3, 1.0, 2.0, 100.0, 0.5);
        // A randomizer returning out-of-range values must not break the bounds.
        $high = new Backoff($policy, static fn (): float => 5.0);
        $low  = new Backoff($policy, static fn (): float => -5.0);

        $delayHigh = $high->delayFor(1); // base 1.0 => envelope [0.5, 1.0]
        $delayLow  = $low->delayFor(1);

        self::assertLessThanOrEqual(1.0, $delayHigh);
        self::assertGreaterThanOrEqual(0.5, $delayHigh);
        self::assertEqualsWithDelta(0.5, $delayLow, 1e-9);
    }
}
