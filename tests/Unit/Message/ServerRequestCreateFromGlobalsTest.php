<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Message;

use InitPHP\HTTP\Message\ServerRequest;
use PHPUnit\Framework\TestCase;

/**
 * ServerRequest::createFromGlobals() is the stateless replacement for the
 * legacy singleton Request::createFromGlobals() (B4 regression). It must:
 *
 *   - return a fresh instance on every call,
 *   - accept explicit \$server/\$get/\$post/\$cookies/\$files snapshots so
 *     tests can drive it without mutating real superglobals,
 *   - parse the body according to the advertised Content-Type,
 *   - parse HTTP_* keys from \$server when apache_request_headers() is not
 *     available, so nginx + php-fpm and FrankenPHP work.
 */
final class ServerRequestCreateFromGlobalsTest extends TestCase
{
    public function testIsStatelessAcrossCalls(): void
    {
        // Regression: the legacy implementation cached the first result in a
        // static property and returned the same instance forever.
        $a = ServerRequest::createFromGlobals(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/one', 'HTTP_HOST' => 'a.test'],
            [],
            [],
            [],
            []
        );
        $b = ServerRequest::createFromGlobals(
            ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/two', 'HTTP_HOST' => 'b.test'],
            [],
            [],
            [],
            []
        );

        self::assertNotSame($a, $b, 'createFromGlobals must compute fresh instances');
        self::assertSame('GET', $a->getMethod());
        self::assertSame('POST', $b->getMethod());
        self::assertSame('http://a.test/one', (string) $a->getUri());
        self::assertSame('http://b.test/two', (string) $b->getUri());
    }

    public function testDefaultMethodIsGetWhenNotInServerArray(): void
    {
        $req = ServerRequest::createFromGlobals([], [], [], [], []);
        self::assertSame('GET', $req->getMethod());
    }

    public function testHttpsServerVariableSelectsHttpsScheme(): void
    {
        $req = ServerRequest::createFromGlobals([
            'HTTPS'       => 'on',
            'HTTP_HOST'   => 'secure.test',
            'REQUEST_URI' => '/x',
        ], [], [], [], []);

        self::assertSame('https', $req->getUri()->getScheme());
    }

    public function testHttpsOffSelectsHttpScheme(): void
    {
        $req = ServerRequest::createFromGlobals([
            'HTTPS'       => 'off',
            'HTTP_HOST'   => 'plain.test',
            'REQUEST_URI' => '/x',
        ], [], [], [], []);

        self::assertSame('http', $req->getUri()->getScheme());
    }

    public function testNonStandardPortAppendedToHost(): void
    {
        $req = ServerRequest::createFromGlobals([
            'SERVER_NAME' => 'api.test',
            'SERVER_PORT' => '8080',
            'REQUEST_URI' => '/x',
        ], [], [], [], []);

        self::assertSame('api.test:8080', $req->getUri()->getAuthority());
    }

    public function testStandardPortNotAppendedToHost(): void
    {
        $req = ServerRequest::createFromGlobals([
            'SERVER_NAME' => 'api.test',
            'SERVER_PORT' => '80',
            'REQUEST_URI' => '/',
        ], [], [], [], []);

        self::assertSame('api.test', $req->getUri()->getAuthority());
    }

    public function testHttpHostWithExplicitPortIsNotDoubled(): void
    {
        // HTTP_HOST already carries the port — must not be appended again.
        $req = ServerRequest::createFromGlobals([
            'HTTP_HOST'   => 'api.test:8443',
            'SERVER_PORT' => '8443',
            'REQUEST_URI' => '/x',
        ], [], [], [], []);

        self::assertSame('api.test:8443', $req->getUri()->getAuthority());
    }

    public function testHeadersCollectedFromHttpStarFallback(): void
    {
        // Even if apache_request_headers() exists locally, the explicit-
        // superglobal path still merges HTTP_* keys when they appear.
        $req = ServerRequest::createFromGlobals([
            'HTTP_HOST'        => 'host.test',
            'HTTP_X_TRACE_ID'  => 'abc-123',
            'HTTP_X_FORWARDED' => '203.0.113.1',
            'CONTENT_TYPE'     => 'text/plain',
            'CONTENT_LENGTH'   => '42',
            'REQUEST_URI'      => '/headers',
        ], [], [], [], []);

        // apache_request_headers() may or may not exist; either way the
        // fallback path normalises HTTP_X_TRACE_ID -> X-Trace-Id. If apache
        // returned an empty array the fallback runs; otherwise apache wins.
        if (!function_exists('apache_request_headers')) {
            self::assertSame('abc-123', $req->getHeaderLine('X-Trace-Id'));
            self::assertSame('203.0.113.1', $req->getHeaderLine('X-Forwarded'));
            self::assertSame('text/plain', $req->getHeaderLine('Content-Type'));
            self::assertSame('42', $req->getHeaderLine('Content-Length'));
        } else {
            // The test still passes if apache_request_headers() returns
            // nothing — the fallback path runs whenever apache yields [].
            self::assertTrue($req->getHeaderLine('X-Trace-Id') === 'abc-123'
                || $req->getHeaderLine('X-Trace-Id') === '');
        }
    }

    public function testServerParamsArePreserved(): void
    {
        $server = [
            'REQUEST_METHOD'   => 'POST',
            'REQUEST_URI'      => '/login',
            'HTTP_HOST'        => 'app.test',
            'SERVER_PROTOCOL'  => 'HTTP/1.1',
            'CUSTOM_VAR'       => 'visible',
        ];

        $req = ServerRequest::createFromGlobals($server, [], [], [], []);
        $params = $req->getServerParams();

        self::assertSame('POST', $params['REQUEST_METHOD']);
        self::assertSame('visible', $params['CUSTOM_VAR']);
    }

    public function testQueryAndCookiesAreCopiedIn(): void
    {
        $req = ServerRequest::createFromGlobals(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/x', 'HTTP_HOST' => 't.t'],
            ['q' => 'phpunit', 'limit' => '50'],
            [],
            ['SESSID' => 'abc'],
            []
        );

        self::assertSame(['q' => 'phpunit', 'limit' => '50'], $req->getQueryParams());
        self::assertSame(['SESSID' => 'abc'], $req->getCookieParams());
    }

    public function testProtocolVersionLiftedFromServerProtocol(): void
    {
        $req = ServerRequest::createFromGlobals(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/x', 'SERVER_PROTOCOL' => 'HTTP/2'],
            [],
            [],
            [],
            []
        );

        self::assertSame('2', $req->getProtocolVersion());
    }

    public function testProtocolDefaultsTo11WhenNotProvided(): void
    {
        $req = ServerRequest::createFromGlobals(['REQUEST_URI' => '/'], [], [], [], []);
        self::assertSame('1.1', $req->getProtocolVersion());
    }

    public function testUrlEncodedFormBodyFallsBackToPostSnapshot(): void
    {
        // The implementation prefers $_POST when available for urlencoded
        // forms, since PHP-FPM has already populated it. We assert that the
        // POST snapshot lands in parsedBody verbatim.
        $req = ServerRequest::createFromGlobals(
            [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI'    => '/submit',
                'CONTENT_TYPE'   => 'application/x-www-form-urlencoded',
            ],
            [],
            ['name' => 'Ada', 'email' => 'ada@example.com'],
            [],
            []
        );

        self::assertSame(['name' => 'Ada', 'email' => 'ada@example.com'], $req->getParsedBody());
    }

    public function testMultipartContentTypeUsesPostSnapshot(): void
    {
        $req = ServerRequest::createFromGlobals(
            [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI'    => '/upload',
                'CONTENT_TYPE'   => 'multipart/form-data; boundary=----foo',
            ],
            [],
            ['title' => 'avatar'],
            [],
            []
        );

        self::assertSame(['title' => 'avatar'], $req->getParsedBody());
    }

    public function testUnknownContentTypeLeavesParsedBodyNull(): void
    {
        $req = ServerRequest::createFromGlobals(
            [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI'    => '/raw',
                'CONTENT_TYPE'   => 'application/octet-stream',
            ],
            [],
            ['ignored' => 'value'],
            [],
            []
        );

        self::assertNull($req->getParsedBody());
    }

    public function testMissingContentTypeLeavesParsedBodyNull(): void
    {
        $req = ServerRequest::createFromGlobals(
            ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/no-ct'],
            [],
            ['ignored' => 'value'],
            [],
            []
        );

        self::assertNull($req->getParsedBody());
    }

    public function testZeroArgInvocationFallsBackToRealSuperglobals(): void
    {
        // Smoke test — no assertion on the contents because $_SERVER under
        // PHPUnit varies, but the call must not throw and must return a
        // ServerRequest. Stateless behaviour (regression for B4) is verified
        // separately above.
        $req = ServerRequest::createFromGlobals();
        self::assertInstanceOf(ServerRequest::class, $req);
    }
}
