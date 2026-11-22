<?php

/**
 * Sleeper.php
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

use function usleep;

/**
 * Production {@see SleeperInterface} backed by usleep(). Converts the
 * fractional-second backoff intervals into microseconds.
 */
final class Sleeper implements SleeperInterface
{
    /**
     * {@inheritDoc}
     */
    public function sleep(float $seconds): void
    {
        if ($seconds <= 0.0) {
            return;
        }

        usleep((int) ($seconds * 1_000_000));
    }
}
