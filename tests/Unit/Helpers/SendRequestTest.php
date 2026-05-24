<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Helpers;

use InitPHP\HTTP\Facade\Client as ClientFacade;
use InitPHP\HTTP\Message\Request;
use InitPHP\HTTP\Tests\Unit\FixtureServerTrait;
use PHPUnit\Framework\TestCase;

/**
 * send_request() is the ergonomic global helper that wraps the PSR-18
 * Client facade with body coercion (array, object-with-__toString,
 * object-with-toArray). Two code paths matter:
 *
 *   - first argument is a PSR-7 RequestInterface  -> forwarded as-is to
 *     ClientFacade::sendRequest()
 *   - first argument is a method string           -> URL required;
 *     body is coerced; ClientFacade::fetch() runs
 *
 * Coverage is end-to-end against the PSR-18 fixture server on a dedicated
 * port (18768) so the body coercion can be verified by inspecting what the
 * /echo endpoint sees on the other side.
 */
final class SendRequestTest extends TestCase
{
    use FixtureServerTrait;

    private const FIXTURE_PORT = 18768;

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

    public function testForwardsPsr7RequestUnchanged(): void
    {
        $request = new Request('GET', self::fixtureBaseUrl() . '/echo', [
            'X-Trace' => 'psr7-branch',
        ]);

        $response = send_request($request);
        $data = json_decode((string) $response->getBody(), true);

        self::assertSame('GET', $data['method']);
        self::assertSame('psr7-branch', $data['headers']['x-trace'] ?? null);
    }

    public function testMethodPlusUrlBranchSendsBodyVerbatim(): void
    {
        $response = send_request(
            'POST',
            self::fixtureBaseUrl() . '/echo',
            ['Content-Type' => 'text/plain'],
            'raw-body'
        );
        $data = json_decode((string) $response->getBody(), true);

        self::assertSame('POST', $data['method']);
        self::assertSame('raw-body', $data['body']);
        self::assertSame('text/plain', $data['headers']['content-type'] ?? null);
    }

    public function testMissingUrlForStringMethodThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        send_request('POST', null, [], 'body');
    }

    public function testArrayBodyIsJsonEncodedAndContentTypeIsAdded(): void
    {
        $response = send_request(
            'POST',
            self::fixtureBaseUrl() . '/echo',
            [],
            ['k' => 'v', 'n' => 42]
        );
        $data = json_decode((string) $response->getBody(), true);

        // Helper json_encode()'s the array...
        self::assertSame('{"k":"v","n":42}', $data['body']);
        // ...and adds Content-Type: application/json; charset=utf-8 when absent.
        self::assertStringContainsString('application/json', $data['headers']['content-type'] ?? '');
    }

    public function testArrayBodyPreservesCallerContentType(): void
    {
        // When the caller explicitly sets Content-Type the helper must NOT
        // overwrite it. Both lower-case and Title-Case key paths must be
        // honoured (array_key_exists is case-sensitive).
        $response = send_request(
            'POST',
            self::fixtureBaseUrl() . '/echo',
            ['Content-Type' => 'application/vnd.custom+json'],
            ['k' => 'v']
        );
        $data = json_decode((string) $response->getBody(), true);

        self::assertSame('application/vnd.custom+json', $data['headers']['content-type'] ?? null);
    }

    public function testObjectWithToStringIsStringified(): void
    {
        $body = new class {
            public function __toString(): string
            {
                return 'stringified';
            }
        };

        $response = send_request(
            'POST',
            self::fixtureBaseUrl() . '/echo',
            ['Content-Type' => 'text/plain'],
            $body
        );
        $data = json_decode((string) $response->getBody(), true);

        self::assertSame('stringified', $data['body']);
    }

    public function testObjectWithToArrayIsJsonEncoded(): void
    {
        $body = new class {
            public function toArray(): array
            {
                return ['serialised' => true, 'count' => 3];
            }
        };

        $response = send_request(
            'POST',
            self::fixtureBaseUrl() . '/echo',
            [],
            $body
        );
        $data = json_decode((string) $response->getBody(), true);

        self::assertSame('{"serialised":true,"count":3}', $data['body']);
        self::assertStringContainsString('application/json', $data['headers']['content-type'] ?? '');
    }

    public function testUnsupportedObjectThrows(): void
    {
        // stdClass has neither __toString nor toArray; the helper bails out
        // with InvalidArgumentException rather than forwarding it to the
        // PSR-18 client (which would also reject it, but with a vaguer error).
        $this->expectException(\InvalidArgumentException::class);
        send_request('POST', self::fixtureBaseUrl() . '/echo', [], new \stdClass());
    }

    public function testToStringTakesPrecedenceOverToArray(): void
    {
        // Helper checks __toString before toArray. Objects that implement
        // both must serialise as a string.
        $body = new class {
            public function __toString(): string
            {
                return 'from-toString';
            }
            public function toArray(): array
            {
                return ['from' => 'toArray'];
            }
        };

        $response = send_request(
            'POST',
            self::fixtureBaseUrl() . '/echo',
            ['Content-Type' => 'text/plain'],
            $body
        );
        $data = json_decode((string) $response->getBody(), true);

        self::assertSame('from-toString', $data['body']);
    }

    public function testStreamInterfaceObjectIsLeftAlone(): void
    {
        // The helper short-circuits the object branch for StreamInterface
        // so the PSR-18 client receives the stream verbatim — no toString,
        // no json_encode, no error.
        $stream = \InitPHP\HTTP\Facade\Factory::createStream('via-stream');
        $response = send_request(
            'POST',
            self::fixtureBaseUrl() . '/echo',
            ['Content-Type' => 'text/plain'],
            $stream
        );
        $data = json_decode((string) $response->getBody(), true);

        self::assertSame('via-stream', $data['body']);
    }
}
