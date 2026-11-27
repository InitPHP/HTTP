<?php

/**
 * RetryAfter.php
 *
 * This file is part of InitPHP HTTP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://muhammetsafak.com
 */

declare(strict_types=1);

namespace InitPHP\HTTP\Client\Retry;

use DateTimeImmutable;
use DateTimeZone;
use Psr\Http\Message\ResponseInterface;

use function is_array;
use function max;
use function preg_match;
use function preg_replace;
use function time;
use function trim;

/**
 * Parses the `Retry-After` response header (RFC 9110 §10.2.3) into a delay in
 * seconds.
 *
 * The header takes one of two forms:
 *   - delay-seconds: one or more ASCII digits and nothing else
 *     (`Retry-After: 120`), or
 *   - an HTTP-date (`Retry-After: Wed, 21 Oct 2015 07:28:00 GMT`), from which
 *     the delay is the difference between that instant and "now". All three
 *     HTTP-date formats of RFC 9110 §5.6.7 are accepted (IMF-fixdate, the
 *     obsolete RFC 850 form and asctime) and are always read as GMT; PHP's
 *     default time zone plays no part.
 *
 * Anything else (a negative or fractional number, a relative phrase such as
 * `tomorrow`, an unparseable date) and an absent header yield `null`, leaving
 * the caller free to fall back to its computed backoff. A past HTTP-date
 * clamps to 0.
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

        if (preg_match('/^\d+$/D', $value) === 1) {
            return (float) $value;
        }

        $timestamp = self::parseHttpDate($value);
        if ($timestamp === null) {
            return null;
        }

        $reference = $now ?? time();

        return (float) max(0, $timestamp - $reference);
    }

    /**
     * Parse an HTTP-date (RFC 9110 §5.6.7) into a Unix timestamp, reading it as
     * GMT. Returns null when the value matches none of the three formats or
     * names a date that does not exist (such as 31 February).
     *
     * @param string $value
     * @return int|null
     */
    private static function parseHttpDate(string $value): ?int
    {
        $utc = new DateTimeZone('UTC');

        // IMF-fixdate: Sun, 06 Nov 1994 08:49:37 GMT
        if (preg_match('/^[A-Z][a-z]{2}, \d{2} [A-Z][a-z]{2} \d{4} \d{2}:\d{2}:\d{2} GMT$/D', $value) === 1) {
            return self::fromFormat('D, d M Y H:i:s \G\M\T', $value, $utc);
        }

        // RFC 850 (obsolete): Sunday, 06-Nov-94 08:49:37 GMT
        if (preg_match('/^[A-Z][a-z]{5,8}, \d{2}-[A-Z][a-z]{2}-\d{2} \d{2}:\d{2}:\d{2} GMT$/D', $value) === 1) {
            $timestamp = self::fromFormat('l, d-M-y H:i:s \G\M\T', $value, $utc);
            if ($timestamp === null) {
                return null;
            }

            // RFC 9110 §5.6.7: a two-digit year that would land more than 50
            // years in the future stands for the most recent past year with
            // the same last two digits.
            $limit = new DateTimeImmutable('+50 years', $utc);
            if ($timestamp > $limit->getTimestamp()) {
                $shifted = (new DateTimeImmutable('@' . $timestamp))->setTimezone($utc)->modify('-100 years');

                return $shifted->getTimestamp();
            }

            return $timestamp;
        }

        // asctime: Sun Nov  6 08:49:37 1994 (the day is space-padded)
        if (preg_match('/^[A-Z][a-z]{2} [A-Z][a-z]{2} [ \d]\d \d{2}:\d{2}:\d{2} \d{4}$/D', $value) === 1) {
            $single = preg_replace('/ {2,}/', ' ', $value);

            return $single === null ? null : self::fromFormat('D M j H:i:s Y', $single, $utc);
        }

        return null;
    }

    /**
     * Build a timestamp from $value with $format in $zone, rejecting values
     * that PHP would silently roll over (31 February, 25:00:00, ...).
     *
     * @param string       $format
     * @param string       $value
     * @param DateTimeZone $zone
     * @return int|null
     */
    private static function fromFormat(string $format, string $value, DateTimeZone $zone): ?int
    {
        $date   = DateTimeImmutable::createFromFormat('!' . $format, $value, $zone);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date->getTimestamp();
    }
}
