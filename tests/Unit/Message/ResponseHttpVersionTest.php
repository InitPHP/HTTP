<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Message;

use InitPHP\HTTP\Message\Response;
use PHPUnit\Framework\TestCase;

/**
 * HTTP version whitelist on Response::__construct(). The supported set is:
 *
 *   "1.0", "1.1", "2", "2.0", "3", "3.0"
 *
 * Anything else (including legacy strings like "http/1.1", patch versions
 * like "1.2"/"2.5", and the empty string) must raise
 * InvalidArgumentException so callers cannot silently transmit a malformed
 * protocol identifier.
 */
final class ResponseHttpVersionTest extends TestCase
{
    /**
     * @return array<string,array{string}>
     */
    public static function acceptedVersionProvider(): array
    {
        return [
            '1.0' => ['1.0'],
            '1.1' => ['1.1'],
            '2'   => ['2'],
            '2.0' => ['2.0'],
            '3'   => ['3'],
            '3.0' => ['3.0'],
        ];
    }

    /**
     * @dataProvider acceptedVersionProvider
     */
    public function testAcceptsSupportedVersion(string $version): void
    {
        $response = new Response(200, [], null, $version);
        self::assertSame($version, $response->getProtocolVersion());
    }

    /**
     * @return array<string,array{string}>
     */
    public static function rejectedVersionProvider(): array
    {
        return [
            'minor 1.2'        => ['1.2'],
            'minor 2.5'        => ['2.5'],
            'major 4.0'        => ['4.0'],
            'prefixed http/1.1'=> ['http/1.1'],
            'empty string'     => [''],
            'plain integer 2'  => ['02'],
            'whitespace'       => [' 1.1 '],
        ];
    }

    /**
     * @dataProvider rejectedVersionProvider
     */
    public function testRejectsUnsupportedVersion(string $version): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Response(200, [], null, $version);
    }
}
