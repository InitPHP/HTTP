<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Client\Retry;

use InitPHP\HTTP\Client\Retry\RetryPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage for the RetryPolicy value object: clamping of out-of-range
 * inputs, the default transient status set, and the retry-decision predicates.
 */
final class RetryPolicyTest extends TestCase
{
    public function testDefaultsAreProductionSane(): void
    {
        $policy = new RetryPolicy();

        self::assertSame(3, $policy->getMaxAttempts());
        self::assertSame(RetryPolicy::DEFAULT_RETRYABLE_STATUS, $policy->getRetryableStatusCodes());
        self::assertTrue($policy->isRetryOnException());
        self::assertTrue($policy->isRespectRetryAfter());
    }

    public function testClampsOutOfRangeInputs(): void
    {
        $policy = new RetryPolicy(0, -5.0, 0.5, -1.0, 5.0);

        self::assertSame(1, $policy->getMaxAttempts(), 'maxAttempts floored at 1');
        self::assertSame(0.0, $policy->getBaseDelay(), 'baseDelay floored at 0');
        self::assertSame(1.0, $policy->getMultiplier(), 'multiplier floored at 1.0');
        self::assertSame(0.0, $policy->getMaxDelay(), 'maxDelay floored at 0');
        self::assertSame(1.0, $policy->getJitter(), 'jitter clamped to [0, 1]');
    }

    public function testDefaultRetryableStatusMembership(): void
    {
        $policy = new RetryPolicy();

        self::assertTrue($policy->isRetryableStatus(429));
        self::assertTrue($policy->isRetryableStatus(503));
        self::assertFalse($policy->isRetryableStatus(200));
        self::assertFalse($policy->isRetryableStatus(404));
        self::assertFalse($policy->isRetryableStatus(501), '501 is not retry-safe by default');
    }

    public function testCustomRetryableStatusOverridesDefault(): void
    {
        $policy = new RetryPolicy(3, 0.1, 2.0, 30.0, 0.0, true, [418, 503]);

        self::assertTrue($policy->isRetryableStatus(418));
        self::assertTrue($policy->isRetryableStatus(503));
        self::assertFalse($policy->isRetryableStatus(429));
    }

    public function testShouldRetryRespectsAttemptCap(): void
    {
        $policy = new RetryPolicy(3);

        self::assertTrue($policy->shouldRetry(1));
        self::assertTrue($policy->shouldRetry(2));
        self::assertFalse($policy->shouldRetry(3), 'cap reached after the 3rd attempt');
        self::assertFalse($policy->shouldRetry(4));
    }
}
