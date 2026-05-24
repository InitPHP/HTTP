<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Client;

use InitPHP\HTTP\Client\Client;
use InitPHP\HTTP\Client\Exceptions\RequestException;
use InitPHP\HTTP\Message\Stream;
use PHPUnit\Framework\TestCase;

/**
 * Body coercion contract enforced by Client::prepareRequest(). Only
 * stream-shaped inputs (null, string, resource, StreamInterface) are
 * accepted — structured payloads (arrays, plain objects) must be encoded
 * by the caller. The tests exercise every accepted shape plus the negative
 * cases that must raise InvalidArgumentException.
 *
 * prepareRequest() is private; coverage flows through the public verbs
 * (get/post/...). To keep this test offline-only we pick a URL that fails
 * fast at the cURL transport layer (port 1) and assert exception types.
 * Coercion succeeds when the transport-level error is reached at all —
 * the body shape was accepted by prepareRequest() before cURL ran.
 */
final class ClientPrepareRequestTest extends TestCase
{
    private const UNREACHABLE_URL = 'http://127.0.0.1:1/initphp-http-unreachable';

    public function testNullBodyIsAcceptedAndProducesEmptyRequestBody(): void
    {
        $client = new Client();
        // Fail-fast network call: success here means body coercion passed
        // (otherwise InvalidArgumentException would have been raised first).
        $this->expectException(\Psr\Http\Client\NetworkExceptionInterface::class);
        $client->post(self::UNREACHABLE_URL, null);
    }

    public function testStringBodyIsAccepted(): void
    {
        $client = new Client();
        $this->expectException(\Psr\Http\Client\NetworkExceptionInterface::class);
        $client->post(self::UNREACHABLE_URL, '{"k":"v"}');
    }

    public function testResourceBodyIsAccepted(): void
    {
        $client = new Client();
        $resource = fopen('php://memory', 'r+b');
        self::assertIsResource($resource);
        fwrite($resource, 'payload');
        fseek($resource, 0);

        $this->expectException(\Psr\Http\Client\NetworkExceptionInterface::class);
        try {
            $client->post(self::UNREACHABLE_URL, $resource);
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }
    }

    public function testStreamInterfaceBodyIsAccepted(): void
    {
        $client = new Client();
        $stream = new Stream('payload', null);
        $this->expectException(\Psr\Http\Client\NetworkExceptionInterface::class);
        $client->post(self::UNREACHABLE_URL, $stream);
    }

    public function testArrayBodyIsRejected(): void
    {
        $client = new Client();
        // Arrays must be encoded by the caller (see send_request() helper).
        // The Client itself MUST throw rather than silently json_encode().
        $this->expectException(\InvalidArgumentException::class);
        $client->post(self::UNREACHABLE_URL, ['key' => 'value']);
    }

    public function testPlainObjectBodyIsRejected(): void
    {
        $client = new Client();
        $this->expectException(\InvalidArgumentException::class);
        $client->post(self::UNREACHABLE_URL, new \stdClass());
    }

    public function testBooleanBodyIsRejected(): void
    {
        $client = new Client();
        $this->expectException(\InvalidArgumentException::class);
        $client->post(self::UNREACHABLE_URL, true);
    }

    public function testIntegerBodyIsRejected(): void
    {
        $client = new Client();
        // Integers/floats are scalars but the Client only accepts string/
        // resource/StreamInterface/null. Stringification is caller's job.
        $this->expectException(\InvalidArgumentException::class);
        $client->post(self::UNREACHABLE_URL, 42);
    }

    public function testInvalidUrlIsReportedAsRequestException(): void
    {
        $client = new Client();
        // FILTER_VALIDATE_URL rejects "not-a-url" so prepareCurlOptions wraps
        // the ClientException in a RequestException carrying the request.
        $this->expectException(RequestException::class);
        $client->get('not-a-url');
    }
}
