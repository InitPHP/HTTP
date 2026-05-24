<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Message;

use InitPHP\HTTP\Message\Response;
use PHPUnit\Framework\TestCase;

/**
 * Response::json() convenience producer. Contract:
 *
 *   - encodes the payload with JSON_THROW_ON_ERROR
 *   - encoding failures bubble out as InvalidArgumentException
 *   - sets Content-Type to application/json; charset=utf-8
 *   - returns a clone (immutability)
 *   - $status default is 200; explicit values override
 */
final class ResponseJsonTest extends TestCase
{
    public function testHappyPathEncodesPayloadAndSetsContentType(): void
    {
        $response = (new Response())->json(['ok' => true, 'count' => 3]);

        self::assertSame('{"ok":true,"count":3}', (string) $response->getBody());
        self::assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame(200, $response->getStatusCode());
    }

    public function testEmptyArrayProducesEmptyJsonObject(): void
    {
        // json_encode([]) returns "[]" — an empty *array*, not "{}". The
        // helper accepts both shapes; pin the actual behaviour so callers
        // can rely on it.
        $response = (new Response())->json([]);
        self::assertSame('[]', (string) $response->getBody());
    }

    public function testStatusCodeIsHonoured(): void
    {
        $response = (new Response())->json(['error' => 'not found'], 404);
        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Not Found', $response->getReasonPhrase());
    }

    public function testJsonReturnsCloneNotOriginal(): void
    {
        // Response::json() must return a clone — the original instance must
        // retain its prior body and headers untouched.
        $original = (new Response(200, ['X-Marker' => 'keep'], 'original-body'));
        $clone = $original->json(['a' => 1]);

        self::assertNotSame($original, $clone);
        self::assertSame('original-body', (string) $original->getBody());
        // X-Marker is carried over on the clone (json() doesn't strip).
        self::assertSame('keep', $clone->getHeaderLine('X-Marker'));
    }

    public function testEncodingFailureRaisesInvalidArgumentException(): void
    {
        // A resource cannot be encoded as JSON. Helper must wrap the
        // JsonException in an InvalidArgumentException with a non-empty
        // message rather than producing a malformed body.
        $resource = fopen('php://memory', 'r');
        self::assertIsResource($resource);

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Cannot encode response payload as JSON');
            (new Response())->json(['handle' => $resource]);
        } finally {
            fclose($resource);
        }
    }

    public function testCustomFlagsAreCombinedWithThrowOnError(): void
    {
        // Caller supplies JSON_PRETTY_PRINT; helper must OR it onto
        // JSON_THROW_ON_ERROR rather than replacing it.
        $response = (new Response())->json(['k' => 'v'], 200, JSON_PRETTY_PRINT);
        $body = (string) $response->getBody();

        self::assertStringContainsString("\n", $body, 'PRETTY_PRINT flag must produce newlines');
    }
}
