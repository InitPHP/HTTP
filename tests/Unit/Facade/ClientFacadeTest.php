<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Facade;

use InitPHP\HTTP\Client\Client;
use InitPHP\HTTP\Facade\Client as ClientFacade;
use PHPUnit\Framework\TestCase;

/**
 * Static facade over the PSR-18 Client singleton. The actual HTTP-verb
 * behaviour is covered by the existing PSR-18 suite and by the new
 * ClientHttpVerbsTest; this suite verifies only the facade plumbing and
 * the configuration-survives-across-calls guarantee that motivated the
 * singleton design.
 */
final class ClientFacadeTest extends TestCase
{
    public function testGetInstanceReturnsTheConcreteClient(): void
    {
        $instance = ClientFacade::getInstance();
        self::assertInstanceOf(Client::class, $instance);
    }

    public function testGetInstanceIsAStableSingleton(): void
    {
        $a = ClientFacade::getInstance();
        $b = ClientFacade::getInstance();
        self::assertSame($a, $b);
    }

    public function testConfigurationPersistsAcrossFacadeAccess(): void
    {
        // Because the facade is a singleton, setUserAgent() through the
        // facade must be visible the next time the facade is accessed.
        // This is the documented design of the static-facade pattern.
        $marker = 'FacadeUA/' . uniqid('', true);
        ClientFacade::setUserAgent($marker);

        self::assertSame($marker, ClientFacade::getUserAgent());
        self::assertSame($marker, ClientFacade::getInstance()->getUserAgent());
    }

    public function testWithUserAgentReturnsCloneThatDoesNotMutateSingleton(): void
    {
        $marker = 'OriginalUA/' . uniqid('', true);
        ClientFacade::setUserAgent($marker);

        // with* returns a clone *of the singleton*, not the singleton
        // itself — mutating that clone must not affect the singleton.
        $clone = ClientFacade::withUserAgent('Cloned/2.0');
        self::assertInstanceOf(Client::class, $clone);
        self::assertSame('Cloned/2.0', $clone->getUserAgent());
        self::assertSame($marker, ClientFacade::getUserAgent());
    }
}
