<?php

declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Client\Retry;

use InitPHP\HTTP\Client\Client;
use InitPHP\HTTP\Client\Retry\RetryPolicy;
use InitPHP\HTTP\Message\Request;
use InitPHP\HTTP\Message\Stream;
use InitPHP\HTTP\Tests\Unit\FixtureServerTrait;
use PHPUnit\Framework\TestCase;

/**
 * Retry against the real cURL transport (PSR-18 fixture server): a retried
 * request must carry the same body as the first attempt, and a request whose
 * body cannot be re-sent must not be retried at all.
 */
final class ClientRetryBodyTest extends TestCase
{
    use FixtureServerTrait;

    private const FIXTURE_PORT = 18769;

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

    /**
     * The fixture's `/echo` answers 200, so a policy that lists 200 as
     * retryable forces a retry and the final response shows which body the
     * last attempt put on the wire.
     */
    private function client(RecordingSleeper $sleeper, ?array $methods = null): Client
    {
        return (new Client())
            ->setSleeper($sleeper)
            ->setRetryPolicy(new RetryPolicy(2, 0.0, 1.0, 1.0, 0.0, true, [200], true, $methods));
    }

    /**
     * @return array{method: string, body: string}
     */
    private function echoed(Request $request, Client $client): array
    {
        $response = $client->sendRequest($request);

        return \json_decode((string) $response->getBody(), true);
    }

    public function testRetryResendsTheFullSeekableBody(): void
    {
        $sleeper = new RecordingSleeper();
        $request = new Request('PUT', self::$baseUrl . '/echo', [], new Stream('payload', null));

        $data = $this->echoed($request, $this->client($sleeper));

        self::assertCount(1, $sleeper->sleeps, 'the 200 was retried once');
        self::assertSame('PUT', $data['method']);
        self::assertSame('payload', $data['body'], 'the retry must carry the body, not an empty one');
    }

    public function testRequestWithNonSeekableBodyIsSentOnceWithItsBody(): void
    {
        $sleeper = new RecordingSleeper();
        $request = new Request('PUT', self::$baseUrl . '/echo', [], ClientRetryTest::nonSeekableBody('payload'));

        $data = $this->echoed($request, $this->client($sleeper));

        self::assertCount(0, $sleeper->sleeps, 'a body that cannot be rewound is never retried');
        self::assertSame('payload', $data['body'], 'the first (and only) attempt carries the body');
    }

    public function testPostIsSentOnceByDefault(): void
    {
        $sleeper = new RecordingSleeper();
        $request = new Request('POST', self::$baseUrl . '/echo', [], new Stream('payload', null));

        $data = $this->echoed($request, $this->client($sleeper));

        self::assertCount(0, $sleeper->sleeps);
        self::assertSame('payload', $data['body']);
    }

    public function testPostIsRetriedWithItsBodyWhenOptedIn(): void
    {
        $sleeper = new RecordingSleeper();
        $request = new Request('POST', self::$baseUrl . '/echo', [], new Stream('payload', null));

        $data = $this->echoed($request, $this->client($sleeper, ['POST']));

        self::assertCount(1, $sleeper->sleeps);
        self::assertSame('payload', $data['body']);
    }
}
