<?php
/**
 * Backoff.php
 *
 * This file is part of InitPHP HTTP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\HTTP\Client\Retry;

use function max;
use function min;
use function mt_getrandmax;
use function mt_rand;

/**
 * Computes exponential-backoff delays for a {@see RetryPolicy}.
 *
 * The *base* delay for an attempt grows geometrically and is capped:
 *
 *     baseDelay(n) = min(maxDelay, baseDelay * multiplier ** (n - 1))
 *
 * The *effective* delay then applies "equal jitter": the capped base delay is
 * randomly reduced by up to the policy's jitter ratio, so the result always
 * falls within `[base * (1 - jitter), base]`. Jitter spreads simultaneous
 * retries out across time, preventing a synchronised retry storm from a fleet
 * of clients that all failed at once.
 *
 * The randomness source is injectable (a closure returning a float in [0, 1)),
 * so tests can pin it and assert exact, reproducible delays.
 */
final class Backoff
{
    private RetryPolicy $policy;

    /** @var callable(): float */
    private $randomizer;

    /**
     * @param RetryPolicy             $policy
     * @param (callable(): float)|null $randomizer Returns a float in [0, 1).
     *        Defaults to a mt_rand()-based uniform source.
     */
    public function __construct(RetryPolicy $policy, ?callable $randomizer = null)
    {
        $this->policy     = $policy;
        $this->randomizer = $randomizer ?? static function (): float {
            return mt_rand() / (mt_getrandmax() + 1);
        };
    }

    /**
     * The deterministic, capped base delay (in seconds) for the given 1-based
     * attempt number — i.e. with jitter excluded. Monotonically non-decreasing
     * in $attempt up to the policy's maxDelay cap.
     *
     * @param int $attempt 1-based attempt index (1 = delay before the 2nd try).
     * @return float
     */
    public function baseDelayFor(int $attempt): float
    {
        if ($attempt < 1) {
            $attempt = 1;
        }

        $delay = $this->policy->getBaseDelay();
        for ($i = 1; $i < $attempt; $i++) {
            $delay *= $this->policy->getMultiplier();
            if ($delay >= $this->policy->getMaxDelay()) {
                return $this->policy->getMaxDelay();
            }
        }

        return min($delay, $this->policy->getMaxDelay());
    }

    /**
     * The effective, jittered delay (in seconds) for the given 1-based attempt
     * number. Guaranteed to fall within `[base * (1 - jitter), base]` where
     * `base` is {@see baseDelayFor()}.
     *
     * @param int $attempt 1-based attempt index.
     * @return float
     */
    public function delayFor(int $attempt): float
    {
        $base = $this->baseDelayFor($attempt);
        $jitter = $this->policy->getJitter();
        if ($jitter <= 0.0 || $base <= 0.0) {
            return $base;
        }

        // Equal jitter: keep a guaranteed floor of (1 - jitter) * base and add a
        // random fraction of the remaining (jitter * base) window on top.
        $floor  = $base * (1.0 - $jitter);
        $window = $base - $floor;
        $rand   = ($this->randomizer)();
        // Clamp the random source into [0, 1) defensively so a misbehaving
        // randomizer can never push the delay outside the documented envelope.
        $rand = min(1.0, max(0.0, $rand));

        return $floor + ($window * $rand);
    }
}
