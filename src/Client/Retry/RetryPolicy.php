<?php
/**
 * RetryPolicy.php
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

use function array_values;
use function in_array;
use function max;
use function min;

/**
 * Immutable configuration for the HTTP client's retry-with-backoff layer.
 *
 * A policy describes *whether* and *how* a failed attempt should be retried:
 * how many attempts to make at most, which HTTP status codes are considered
 * transient, whether transport (network) exceptions should be retried, and the
 * exponential-backoff envelope (base delay, multiplier, cap and jitter) used to
 * space the attempts out.
 *
 * The default-constructed policy retries on the conventional transient set —
 * connection failures plus HTTP 408, 425, 429, 500, 502, 503 and 504 — which is
 * a sensible production default. The client itself stays single-attempt unless
 * a policy is explicitly attached, preserving full backward compatibility.
 */
final class RetryPolicy
{
    /**
     * HTTP status codes treated as transient by default: request timeout,
     * "too early", rate limiting and the retry-safe 5xx family.
     */
    public const DEFAULT_RETRYABLE_STATUS = [408, 425, 429, 500, 502, 503, 504];

    /** Total number of attempts (the initial try plus retries). */
    private int $maxAttempts;

    /** Base delay, in seconds, for the first backoff interval. */
    private float $baseDelay;

    /** Multiplier applied to the delay after each failed attempt. */
    private float $multiplier;

    /** Hard upper bound, in seconds, on any single backoff interval. */
    private float $maxDelay;

    /**
     * Jitter ratio in [0.0, 1.0]. 0.0 disables jitter (pure exponential);
     * a value of e.g. 0.5 lets the computed delay be randomly reduced by up to
     * 50%, spreading retries out to avoid a thundering-herd retry storm.
     */
    private float $jitter;

    /**
     * Whether to retry when a transport-level failure (DNS, TCP, TLS, timeout)
     * surfaces as a {@see \Psr\Http\Client\NetworkExceptionInterface}.
     */
    private bool $retryOnException;

    /**
     * Response status codes that should trigger a retry.
     *
     * @var list<int>
     */
    private array $retryableStatusCodes;

    /**
     * Whether a `Retry-After` response header (when present and parseable on a
     * retryable response) should override the computed backoff delay.
     */
    private bool $respectRetryAfter;

    /**
     * @param int        $maxAttempts          Total attempts (>= 1). Clamped up to 1.
     * @param float      $baseDelay            First backoff interval in seconds (>= 0).
     * @param float      $multiplier           Per-attempt growth factor (>= 1.0).
     * @param float      $maxDelay             Cap on any single interval in seconds (>= 0).
     * @param float      $jitter               Jitter ratio in [0.0, 1.0].
     * @param bool       $retryOnException     Retry on transport exceptions.
     * @param list<int>|null $retryableStatusCodes Override the default transient status set.
     * @param bool       $respectRetryAfter    Honour a `Retry-After` header when present.
     */
    public function __construct(
        int $maxAttempts = 3,
        float $baseDelay = 0.1,
        float $multiplier = 2.0,
        float $maxDelay = 30.0,
        float $jitter = 0.5,
        bool $retryOnException = true,
        ?array $retryableStatusCodes = null,
        bool $respectRetryAfter = true
    ) {
        $this->maxAttempts          = max(1, $maxAttempts);
        $this->baseDelay            = max(0.0, $baseDelay);
        $this->multiplier           = max(1.0, $multiplier);
        $this->maxDelay             = max(0.0, $maxDelay);
        $this->jitter               = min(1.0, max(0.0, $jitter));
        $this->retryOnException     = $retryOnException;
        $this->retryableStatusCodes = $retryableStatusCodes === null
            ? self::DEFAULT_RETRYABLE_STATUS
            : array_values($retryableStatusCodes);
        $this->respectRetryAfter    = $respectRetryAfter;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function getBaseDelay(): float
    {
        return $this->baseDelay;
    }

    public function getMultiplier(): float
    {
        return $this->multiplier;
    }

    public function getMaxDelay(): float
    {
        return $this->maxDelay;
    }

    public function getJitter(): float
    {
        return $this->jitter;
    }

    public function isRetryOnException(): bool
    {
        return $this->retryOnException;
    }

    /**
     * @return list<int>
     */
    public function getRetryableStatusCodes(): array
    {
        return $this->retryableStatusCodes;
    }

    public function isRespectRetryAfter(): bool
    {
        return $this->respectRetryAfter;
    }

    /**
     * Whether the given response status code is in the retryable set.
     */
    public function isRetryableStatus(int $statusCode): bool
    {
        return in_array($statusCode, $this->retryableStatusCodes, true);
    }

    /**
     * Whether another attempt is permitted after $attempt attempts have already
     * been made. $attempt is 1-based: 1 means the first try has just completed.
     */
    public function shouldRetry(int $attempt): bool
    {
        return $attempt < $this->maxAttempts;
    }
}
