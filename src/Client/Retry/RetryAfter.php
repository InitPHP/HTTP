<?php
/**
 * RetryAfter.php
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

use Psr\Http\Message\ResponseInterface;

use function ctype_digit;
use function max;
use function strtotime;
use function time;
use function trim;

/**
 * Parses the `Retry-After` response header (RFC 7231 §7.1.3) into a delay in
 * seconds.
 *
 * The header takes one of two forms:
 *   - a non-negative integer number of seconds (`Retry-After: 120`), or
 *   - an HTTP-date (`Retry-After: Wed, 21 Oct 2015 07:28:00 GMT`), from which
 *     the delay is the difference between that instant and "now".
 *
 * Unparseable or absent headers yield `null`, leaving the caller free to fall
 * back to its computed backoff. A past HTTP-date clamps to 0.
 */
final class RetryAfter
{
    /**
     * Extract the `Retry-After` delay (in seconds) from a response, or null when
     * the header is absent or cannot be parsed.
     *
     * @param ResponseInterface $response
     * @param int|null          $now Unix timestamp used as "now" for HTTP-date
     *        parsing; defaults to time(). Injectable for deterministic tests.
     * @return float|null
     */
    public static function fromResponse(ResponseInterface $response, ?int $now = null): ?float
    {
        if (!$response->hasHeader('Retry-After')) {
            return null;
        }

        return self::parse($response->getHeaderLine('Retry-After'), $now);
    }

    /**
     * Parse a raw `Retry-After` header value into a non-negative delay in
     * seconds, or null when it is neither a valid delay-seconds nor a valid
     * HTTP-date.
     *
     * @param string   $value
     * @param int|null $now
     * @return float|null
     */
    public static function parse(string $value, ?int $now = null): ?float
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return (float) $value;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        $reference = $now ?? time();

        return (float) max(0, $timestamp - $reference);
    }
}
