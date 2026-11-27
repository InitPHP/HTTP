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

        $timestamp = self::parseHttpDate($value, $now);
        if ($timestamp === null) {
            return null;
        }

        $reference = $now ?? time();

        return (float) max(0, $timestamp - $reference);
    }

    /**
     * Parse an HTTP-date (RFC 9110 §5.6.7) into a Unix timestamp, reading it as
     * GMT. Returns null when the value matches none of the three formats,
     * names a date that does not exist (such as 31 February) or carries a day
     * name that does not belong to its date (`Mon, 21 Oct 2015 ...` is a
     * Wednesday).
     *
     * The day name is checked here instead of being left to
     * DateTimeImmutable::createFromFormat(): its `D` and `l` tokens do not
     * validate the name, they shift the date to the next such weekday.
     *
     * @param string   $value
     * @param int|null $now Reference "now" for the RFC 850 two-digit year rule.
     * @return int|null
     */
    private static function parseHttpDate(string $value, ?int $now): ?int
    {
        $utc = new DateTimeZone('UTC');

        // IMF-fixdate: Sun, 06 Nov 1994 08:49:37 GMT
        if (preg_match('/^([A-Z][a-z]{2}), (\d{2} [A-Z][a-z]{2} \d{4} \d{2}:\d{2}:\d{2}) GMT$/D', $value, $m) === 1) {
            $date = self::fromFormat('d M Y H:i:s', $m[2], $utc);

            return self::hasWeekday($date, 'D', $m[1]) ? $date?->getTimestamp() : null;
        }

        // RFC 850 (obsolete): Sunday, 06-Nov-94 08:49:37 GMT
        if (preg_match('/^([A-Z][a-z]{5,8}), (\d{2})-([A-Z][a-z]{2})-(\d{2}) (\d{2}:\d{2}:\d{2}) GMT$/D', $value, $m) === 1) {
            // RFC 9110 §5.6.7: a two-digit year that would land more than 50
            // years in the future stands for the most recent past year with
            // the same last two digits. PHP's own `y` token uses a fixed 1970
            // pivot instead, so the century is chosen here.
            $limit = (new DateTimeImmutable('@' . ($now ?? time())))->modify('+50 years')->getTimestamp();
            foreach (['20', '19'] as $century) {
                $date = self::fromFormat('d-M-Y H:i:s', $m[2] . '-' . $m[3] . '-' . $century . $m[4] . ' ' . $m[5], $utc);
                if ($date !== null && $date->getTimestamp() <= $limit) {
                    break;
                }
            }

            return self::hasWeekday($date, 'l', $m[1]) ? $date?->getTimestamp() : null;
        }

        // asctime: Sun Nov  6 08:49:37 1994 (the day is space-padded)
        if (preg_match('/^([A-Z][a-z]{2}) ([A-Z][a-z]{2}) ([ \d]\d) (\d{2}:\d{2}:\d{2}) (\d{4})$/D', $value, $m) === 1) {
            $date = self::fromFormat('j M Y H:i:s', trim($m[3]) . ' ' . $m[2] . ' ' . $m[5] . ' ' . $m[4], $utc);

            return self::hasWeekday($date, 'D', $m[1]) ? $date?->getTimestamp() : null;
        }

        return null;
    }

    /**
     * Whether $date falls on the weekday named by $name. $token is the
     * DateTimeInterface::format() token for the name's form: `D` for the
     * three-letter abbreviation, `l` for the full name.
     *
     * @param DateTimeImmutable|null $date
     * @param string                 $token
     * @param string                 $name
     * @return bool
     */
    private static function hasWeekday(?DateTimeImmutable $date, string $token, string $name): bool
    {
        return $date !== null && $date->format($token) === $name;
    }

    /**
     * Build a date from $value with $format in $zone, rejecting values that
     * PHP would silently roll over (31 February, 25:00:00, ...).
     *
     * @param string       $format
     * @param string       $value
     * @param DateTimeZone $zone
     * @return DateTimeImmutable|null
     */
    private static function fromFormat(string $format, string $value, DateTimeZone $zone): ?DateTimeImmutable
    {
        $date   = DateTimeImmutable::createFromFormat('!' . $format, $value, $zone);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date;
    }
}
