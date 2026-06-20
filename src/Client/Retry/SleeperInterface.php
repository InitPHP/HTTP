<?php
/**
 * SleeperInterface.php
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

/**
 * Abstraction over "wait this many seconds" so the retry layer's pacing is
 * decoupled from real wall-clock time. Production uses {@see Sleeper}, which
 * delegates to usleep(); tests inject a fake that merely records the requested
 * durations, keeping the suite fast and deterministic.
 */
interface SleeperInterface
{
    /**
     * Block for $seconds seconds. Implementations must treat negative or zero
     * inputs as a no-op.
     *
     * @param float $seconds
     * @return void
     */
    public function sleep(float $seconds): void;
}
