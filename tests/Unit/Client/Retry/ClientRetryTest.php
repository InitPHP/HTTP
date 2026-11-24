<?php

declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Client\Retry;

use InitPHP\HTTP\Client\Exceptions\NetworkException;
use InitPHP\HTTP\Client\Retry\RetryPolicy;
use InitPHP\HTTP\Message\Request;
use InitPHP\HTTP\Message\Response;
use InitPHP\HTTP\Message\Stream;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end behaviour of the opt-in retry-with-backoff layer wired into
 * Client::sendRequest(). Every test scripts the transport seam so no network is
 * touched, and injects a recording sleeper so backoff timing is asserted
 * without real delays. The default (policy-less) client must stay single-shot.
 */
final class ClientRetryTest extends TestCase
{
    private function request(): Request
    {
        return new Request('GET', 'https://api.example.com/resource');
    }

    private function response(int $status, array $headers = []): Response
    {
        return new Response($status, $headers);
    }

    private function networkError(): NetworkException
    {
        return new NetworkException($this->request(), 'connection refused', 7);
    }

    public function testDefaultClientWithoutPolicyMakesSingleAttempt(): void
    {
        // No policy attached => fully backward compatible, exactly one transport
        // call, the 503 is returned (PSR-18: 5xx is returned, not thrown).
        $client = new ScriptedClient([$this->response(503)]);

        $response = $client->sendRequest($this->request());

        self::assertSame(503, $response->getStatusCode());
        self::assertSame(1, $client->calls);
    }

    public function testSucceedsAfterRetry(): void
    {
        $sleeper = new RecordingSleeper();
        $client = new ScriptedClient([
            $this->response(503),
            $this->response(503),
            $this->response(200),
        ]);
        $client->setSleeper($sleeper)
            ->setRetryPolicy(new RetryPolicy(3, 0.1, 2.0, 30.0, 0.0));

        $response = $client->sendRequest($this->request());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(3, $client->calls, 'two failures then a success = three attempts');
        self::assertCount(2, $sleeper->sleeps, 'slept once before each of the two retries');
    }

    public function testGivesUpAfterMaxAttemptsAndReturnsLastResponse(): void
    {
        $sleeper = new RecordingSleeper();
        $client = new ScriptedClient([
            $this->response(503),
            $this->response(503),
            $this->response(503),
        ]);
        $client->setSleeper($sleeper)
            ->setRetryPolicy(new RetryPolicy(3, 0.1, 2.0, 30.0, 0.0));

        $response = $client->sendRequest($this->request());

        // Cap reached: the last (still-retryable) response is returned, never
        // thrown, and there is no sleep after the final failed attempt.
        self::assertSame(503, $response->getStatusCode());
        self::assertSame(3, $client->calls);
        self::assertCount(2, $sleeper->sleeps);
    }

    public function testBackoffGrowsExponentially(): void
    {
        $sleeper = new RecordingSleeper();
        $client = new ScriptedClient([
            $this->response(500),
            $this->response(500),
            $this->response(500),
            $this->response(200),
        ]);
        // Jitter disabled => deterministic geometric sequence 0.1, 0.2, 0.4.
        $client->setSleeper($sleeper)
            ->setRetryPolicy(new RetryPolicy(4, 0.1, 2.0, 30.0, 0.0));

        $client->sendRequest($this->request());

        self::assertCount(3, $sleeper->sleeps);
        self::assertEqualsWithDelta(0.1, $sleeper->sleeps[0], 1e-9);
        self::assertEqualsWithDelta(0.2, $sleeper->sleeps[1], 1e-9);
        self::assertEqualsWithDelta(0.4, $sleeper->sleeps[2], 1e-9);
        self::assertGreaterThan($sleeper->sleeps[0], $sleeper->sleeps[1]);
        self::assertGreaterThan($sleeper->sleeps[1], $sleeper->sleeps[2]);
    }

    public function testJitterStaysWithinBounds(): void
    {
        $sleeper = new RecordingSleeper();
        // randomizer pinned to 0.0 => each delay collapses to its floor:
        // base * (1 - jitter). With base 1.0 and jitter 0.5 that is 0.5.
        $client = new ScriptedClient([
            $this->response(503),
            $this->response(200),
        ]);
        $client->setSleeper($sleeper)
            ->setJitterRandomizer(static fn(): float => 0.0)
            ->setRetryPolicy(new RetryPolicy(2, 1.0, 2.0, 30.0, 0.5));

        $client->sendRequest($this->request());

        self::assertCount(1, $sleeper->sleeps);
        // For attempt #1 base = 1.0; jittered delay must be in [0.5, 1.0].
        self::assertGreaterThanOrEqual(0.5, $sleeper->sleeps[0]);
        self::assertLessThanOrEqual(1.0, $sleeper->sleeps[0]);
        self::assertEqualsWithDelta(0.5, $sleeper->sleeps[0], 1e-9);
    }

    public function testNonRetryableStatusIsNotRetried(): void
    {
        $sleeper = new RecordingSleeper();
        // 404 is not in the default retryable set; must be returned immediately.
        $client = new ScriptedClient([$this->response(404)]);
        $client->setSleeper($sleeper)
            ->setRetryPolicy(new RetryPolicy(5, 0.1, 2.0, 30.0, 0.0));

        $response = $client->sendRequest($this->request());

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(1, $client->calls);
        self::assertCount(0, $sleeper->sleeps);
    }

    public function testRetryAfterHeaderIsHonoured(): void
    {
        $sleeper = new RecordingSleeper();
        $client = new ScriptedClient([
            $this->response(429, ['Retry-After' => '7']),
            $this->response(200),
        ]);
        // Backoff base is tiny (0.05) but Retry-After: 7 must win.
        $client->setSleeper($sleeper)
            ->setRetryPolicy(new RetryPolicy(3, 0.05, 2.0, 30.0, 0.0));

        $response = $client->sendRequest($this->request());

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $sleeper->sleeps);
        self::assertEqualsWithDelta(7.0, $sleeper->sleeps[0], 1e-9);
    }

    public function testRetryAfterIgnoredWhenPolicyDisablesIt(): void
    {
        $sleeper = new RecordingSleeper();
        $client = new ScriptedClient([
            $this->response(429, ['Retry-After' => '7']),
            $this->response(200),
        ]);
        // respectRetryAfter = false => fall back to the computed 0.05 backoff.
        $client->setSleeper($sleeper)
            ->setRetryPolicy(new RetryPolicy(3, 0.05, 2.0, 30.0, 0.0, true, null, false));

        $client->sendRequest($this->request());

        self::assertCount(1, $sleeper->sleeps);
        self::assertEqualsWithDelta(0.05, $sleeper->sleeps[0], 1e-9);
    }

    public function testTransportExceptionIsRetriedThenSucceeds(): void
    {
        $sleeper = new RecordingSleeper();
        $client = new ScriptedClient([
            $this->networkError(),
            $this->response(200),
        ]);
        $client->setSleeper($sleeper)
            ->setRetryPolicy(new RetryPolicy(3, 0.1, 2.0, 30.0, 0.0));

        $response = $client->sendRequest($this->request());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(2, $client->calls);
        self::assertCount(1, $sleeper->sleeps);
    }

    public function testTransportExceptionRethrownAfterMaxAttempts(): void
    {
        $sleeper = new RecordingSleeper();
        $client = new ScriptedClient([
            $this->networkError(),
            $this->networkError(),
        ]);
        $client->setSleeper($sleeper)
            ->setRetryPolicy(new RetryPolicy(2, 0.1, 2.0, 30.0, 0.0));

        $this->expectException(NetworkException::class);
        try {
            $client->sendRequest($this->request());
        } finally {
            self::assertSame(2, $client->calls);
            self::assertCount(1, $sleeper->sleeps, 'one sleep between the two attempts, none after the last');
        }
    }

    public function testTransportExceptionNotRetriedWhenPolicyDisablesIt(): void
    {
        $sleeper = new RecordingSleeper();
        $client = new ScriptedClient([$this->networkError()]);
        // retryOnException = false => the first network error is rethrown at once.
        $client->setSleeper($sleeper)
            ->setRetryPolicy(new RetryPolicy(5, 0.1, 2.0, 30.0, 0.0, false));

        $this->expectException(NetworkException::class);
        try {
            $client->sendRequest($this->request());
        } finally {
            self::assertSame(1, $client->calls);
            self::assertCount(0, $sleeper->sleeps);
        }
    }

    public function testCustomRetryableStatusSet(): void
    {
        $sleeper = new RecordingSleeper();
        // Only 418 is retryable here; the default 503 must NOT be retried.
        $client = new ScriptedClient([$this->response(503)]);
        $client->setSleeper($sleeper)
            ->setRetryPolicy(new RetryPolicy(4, 0.1, 2.0, 30.0, 0.0, true, [418]));

        $response = $client->sendRequest($this->request());

        self::assertSame(503, $response->getStatusCode());
        self::assertSame(1, $client->calls);
    }

    public function testWithRetryPolicyReturnsCloneAndKeepsOriginalSingleShot(): void
    {
        $client = new ScriptedClient([$this->response(503), $this->response(200)]);
        $clone = $client->withRetryPolicy(new RetryPolicy(3, 0.0, 2.0, 1.0, 0.0));

        self::assertNotSame($client, $clone);
        self::assertNull($client->getRetryPolicy());
        self::assertInstanceOf(RetryPolicy::class, $clone->getRetryPolicy());
    }

    public function testNonIdempotentMethodIsNotRetriedOnRetryableStatus(): void
    {
        $sleeper = new RecordingSleeper();
        $client = new ScriptedClient([$this->response(503), $this->response(200)]);
        $client->setSleeper($sleeper)
            ->setRetryPolicy(new RetryPolicy(3, 0.1, 2.0, 30.0, 0.0));

        $response = $client->sendRequest(new Request('POST', 'https://api.example.com/orders'));

        self::assertSame(503, $response->getStatusCode(), 'the first response is returned as is');
        self::assertSame(1, $client->calls);
        self::assertCount(0, $sleeper->sleeps);
    }

    public function testNonIdempotentMethodIsNotRetriedOnTransportException(): void
    {
        $sleeper = new RecordingSleeper();
        $client = new ScriptedClient([$this->networkError(), $this->response(200)]);
        $client->setSleeper($sleeper)
            ->setRetryPolicy(new RetryPolicy(3, 0.1, 2.0, 30.0, 0.0));

        try {
            $client->sendRequest(new Request('PATCH', 'https://api.example.com/orders/1'));
            self::fail('The first transport exception must propagate.');
        } catch (NetworkException $e) {
            self::assertSame(1, $client->calls);
            self::assertCount(0, $sleeper->sleeps);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function idempotentMethodProvider(): array
    {
        return [
            'GET'     => ['GET'],
            'HEAD'    => ['HEAD'],
            'OPTIONS' => ['OPTIONS'],
            'TRACE'   => ['TRACE'],
            'PUT'     => ['PUT'],
            'DELETE'  => ['DELETE'],
            'put (lower case)' => ['put'],
        ];
    }

    /**
     * @dataProvider idempotentMethodProvider
     */
    public function testIdempotentMethodsAreRetriedByDefault(string $method): void
    {
        $sleeper = new RecordingSleeper();
        $client = new ScriptedClient([$this->response(503), $this->response(200)]);
        $client->setSleeper($sleeper)
            ->setRetryPolicy(new RetryPolicy(3, 0.1, 2.0, 30.0, 0.0));

        $response = $client->sendRequest(new Request($method, 'https://api.example.com/resource'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(2, $client->calls);
    }

    public function testNonIdempotentMethodCanBeOptedIn(): void
    {
        $sleeper = new RecordingSleeper();
        $client = new ScriptedClient([$this->response(503), $this->response(201)]);
        $client->setSleeper($sleeper)
            ->setRetryPolicy(new RetryPolicy(3, 0.1, 2.0, 30.0, 0.0, retryableMethods: ['POST']));

        $response = $client->sendRequest(new Request('POST', 'https://api.example.com/orders'));

        self::assertSame(201, $response->getStatusCode());
        self::assertSame(2, $client->calls);
    }

    public function testMethodOutsideCustomListIsNotRetried(): void
    {
        $client = new ScriptedClient([$this->response(503), $this->response(200)]);
        $client->setSleeper(new RecordingSleeper())
            ->setRetryPolicy(new RetryPolicy(3, 0.1, 2.0, 30.0, 0.0, retryableMethods: ['POST']));

        $response = $client->sendRequest($this->request());

        self::assertSame(503, $response->getStatusCode(), 'GET is not in the custom list');
        self::assertSame(1, $client->calls);
    }

    public function testRetryAfterIsCappedByMaxDelay(): void
    {
        $sleeper = new RecordingSleeper();
        $client = new ScriptedClient([
            $this->response(503, ['Retry-After' => '3600']),
            $this->response(200),
        ]);
        $client->setSleeper($sleeper)
            ->setRetryPolicy(new RetryPolicy(3, 0.05, 2.0, 30.0, 0.0));

        $client->sendRequest($this->request());

        self::assertCount(1, $sleeper->sleeps);
        self::assertEqualsWithDelta(30.0, $sleeper->sleeps[0], 1e-9);
    }

    public function testHttpDateRetryAfterIsCappedByMaxDelay(): void
    {
        $sleeper = new RecordingSleeper();
        $date = \gmdate('D, d M Y H:i:s', \time() + 7200) . ' GMT';
        $client = new ScriptedClient([
            $this->response(429, ['Retry-After' => $date]),
            $this->response(200),
        ]);
        $client->setSleeper($sleeper)
            ->setRetryPolicy(new RetryPolicy(3, 0.05, 2.0, 10.0, 0.0));

        $client->sendRequest($this->request());

        self::assertEqualsWithDelta(10.0, $sleeper->sleeps[0], 1e-9);
    }

    public function testRetryAfterBelowMaxDelayIsUsedAsIs(): void
    {
        $sleeper = new RecordingSleeper();
        $client = new ScriptedClient([
            $this->response(429, ['Retry-After' => '2']),
            $this->response(200),
        ]);
        $client->setSleeper($sleeper)
            ->setRetryPolicy(new RetryPolicy(3, 0.05, 2.0, 30.0, 0.0));

        $client->sendRequest($this->request());

        self::assertEqualsWithDelta(2.0, $sleeper->sleeps[0], 1e-9);
    }

    public function testRequestWithNonSeekableBodyIsNotRetriedOnStatus(): void
    {
        $sleeper = new RecordingSleeper();
        $client = new ScriptedClient([$this->response(503), $this->response(200)]);
        $client->setSleeper($sleeper)
            ->setRetryPolicy(new RetryPolicy(3, 0.1, 2.0, 30.0, 0.0));

        $response = $client->sendRequest(
            new Request('PUT', 'https://api.example.com/resource', [], self::nonSeekableBody('payload')),
        );

        self::assertSame(503, $response->getStatusCode(), 'the first response is returned as is');
        self::assertSame(1, $client->calls);
        self::assertCount(0, $sleeper->sleeps);
    }

    public function testRequestWithNonSeekableBodyIsNotRetriedOnException(): void
    {
        $client = new ScriptedClient([$this->networkError(), $this->response(200)]);
        $client->setSleeper(new RecordingSleeper())
            ->setRetryPolicy(new RetryPolicy(3, 0.1, 2.0, 30.0, 0.0));

        try {
            $client->sendRequest(
                new Request('PUT', 'https://api.example.com/resource', [], self::nonSeekableBody('payload')),
            );
            self::fail('The first transport exception must propagate.');
        } catch (NetworkException $e) {
            self::assertSame(1, $client->calls);
        }
    }

    public function testRequestWithSeekableBodyIsStillRetried(): void
    {
        $client = new ScriptedClient([$this->response(503), $this->response(200)]);
        $client->setSleeper(new RecordingSleeper())
            ->setRetryPolicy(new RetryPolicy(3, 0.1, 2.0, 30.0, 0.0));

        $response = $client->sendRequest(
            new Request('PUT', 'https://api.example.com/resource', [], new Stream('payload', null)),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(2, $client->calls);
    }

    /**
     * A readable stream that cannot be rewound: one end of a socket pair that
     * already holds the payload.
     */
    public static function nonSeekableBody(string $payload): Stream
    {
        $pair = \stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
        self::assertIsArray($pair);
        \fwrite($pair[0], $payload);
        \fclose($pair[0]);
        $body = new Stream($pair[1]);
        self::assertFalse($body->isSeekable());

        return $body;
    }
}
