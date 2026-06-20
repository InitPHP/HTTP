<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Client\Retry;

use InitPHP\HTTP\Client\Retry\SleeperInterface;

/**
 * Test double for {@see SleeperInterface}: never actually sleeps, just records
 * every requested duration so tests can assert how long the retry loop *would*
 * have waited — and that backoff grows — without slowing the suite down.
 */
final class RecordingSleeper implements SleeperInterface
{
    /** @var list<float> */
    public array $sleeps = [];

    public function sleep(float $seconds): void
    {
        $this->sleeps[] = $seconds;
    }
}
