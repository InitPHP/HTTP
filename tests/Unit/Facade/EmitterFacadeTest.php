<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Facade;

use InitPHP\HTTP\Emitter\Emitter;
use InitPHP\HTTP\Facade\Emitter as EmitterFacade;
use PHPUnit\Framework\TestCase;

/**
 * Static facade over the Emitter singleton. The instance is created with
 * strict mode enabled per the facade's documented contract; we verify that
 * round-trip plus the singleton-stability guarantee callers rely on.
 *
 * We do NOT call `emit()` here — the EmitterStrictModeTest already covers
 * end-to-end emission and would require a clean SAPI buffer that PHPUnit
 * cannot offer in-process.
 */
final class EmitterFacadeTest extends TestCase
{
    public function testGetInstanceReturnsTheConcreteEmitter(): void
    {
        $instance = EmitterFacade::getInstance();
        self::assertInstanceOf(Emitter::class, $instance);
    }

    public function testGetInstanceIsAStableSingleton(): void
    {
        $a = EmitterFacade::getInstance();
        $b = EmitterFacade::getInstance();
        self::assertSame($a, $b);
    }
}
