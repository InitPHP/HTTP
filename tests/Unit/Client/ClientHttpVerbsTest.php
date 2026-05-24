<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Client;

use InitPHP\HTTP\Client\Client;
use InitPHP\HTTP\Tests\Unit\FixtureServerTrait;
use PHPUnit\Framework\TestCase;

/**
 * Convenience HTTP verb methods on Client (fetch/get/post/put/patch/delete/
 * head) end-to-end against the PSR-18 fixture server. The PSR-18 suite
 * already covers sendRequest()/headers/body/cookies on port 18766; this
 * suite runs on a different port (18767) so both can execute in parallel
 * without colliding.
 */
final class ClientHttpVerbsTest extends TestCase
{
    use FixtureServerTrait;

    private const FIXTURE_PORT = 18767;

    private static function fixturePort(): int
    {
        return self::FIXTURE_PORT;
    }

    public static function setUpBeforeClass(): void
    {
        self::bootFixtureServer();
    }

    public static function tearDownAfterClass(): void
    {
        self::shutdownFixtureServer();
    }

    public function testFetchUsesGetByDefault(): void
    {
        $client = new Client();
        $response = $client->fetch(self::fixtureBaseUrl() . '/echo');

        self::assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        self::assertSame('GET', $data['method']);
    }

    public function testFetchHonoursMethodFromDetailsArray(): void
    {
        $client = new Client();
        $response = $client->fetch(self::fixtureBaseUrl() . '/echo', [
            'method'  => 'POST',
            'body'    => 'hello',
            'headers' => ['X-Trace' => 'fetch'],
        ]);

        $data = json_decode((string) $response->getBody(), true);
        self::assertSame('POST', $data['method']);
        self::assertSame('hello', $data['body']);
        self::assertSame('fetch', $data['headers']['x-trace'] ?? null);
    }

    public function testFetchIsCaseInsensitiveOnDetailsKeys(): void
    {
        $client = new Client();
        // METHOD/HEADERS in upper case — array_change_key_case must lower them.
        $response = $client->fetch(self::fixtureBaseUrl() . '/echo', [
            'METHOD'  => 'POST',
            'HEADERS' => ['X-Case' => 'upper'],
        ]);

        $data = json_decode((string) $response->getBody(), true);
        self::assertSame('POST', $data['method']);
        self::assertSame('upper', $data['headers']['x-case'] ?? null);
    }

    public function testGetReachesEchoEndpoint(): void
    {
        $client = new Client();
        $response = $client->get(self::fixtureBaseUrl() . '/echo');

        self::assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        self::assertSame('GET', $data['method']);
    }

    public function testPostSendsBody(): void
    {
        $client = new Client();
        $response = $client->post(self::fixtureBaseUrl() . '/echo', '{"x":1}', [
            'Content-Type' => 'application/json',
        ]);

        $data = json_decode((string) $response->getBody(), true);
        self::assertSame('POST', $data['method']);
        self::assertSame('{"x":1}', $data['body']);
    }

    public function testPutSendsBody(): void
    {
        $client = new Client();
        $response = $client->put(self::fixtureBaseUrl() . '/echo', 'put-body');

        $data = json_decode((string) $response->getBody(), true);
        self::assertSame('PUT', $data['method']);
        self::assertSame('put-body', $data['body']);
    }

    public function testPatchSendsBody(): void
    {
        $client = new Client();
        $response = $client->patch(self::fixtureBaseUrl() . '/echo', 'patch-body');

        $data = json_decode((string) $response->getBody(), true);
        self::assertSame('PATCH', $data['method']);
        self::assertSame('patch-body', $data['body']);
    }

    public function testDeleteReachesEchoEndpoint(): void
    {
        $client = new Client();
        $response = $client->delete(self::fixtureBaseUrl() . '/echo');

        $data = json_decode((string) $response->getBody(), true);
        self::assertSame('DELETE', $data['method']);
    }

    public function testHeadReturnsHeadersOnly(): void
    {
        $client = new Client();
        $response = $client->head(self::fixtureBaseUrl() . '/echo');

        self::assertSame(200, $response->getStatusCode());
        // CURLOPT_NOBODY ensures the body is discarded on the wire.
        self::assertSame('', (string) $response->getBody());
    }
}
