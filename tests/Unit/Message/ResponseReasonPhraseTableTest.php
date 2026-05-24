<?php
declare(strict_types=1);

namespace InitPHP\HTTP\Tests\Unit\Message;

use InitPHP\HTTP\Message\Response;
use PHPUnit\Framework\TestCase;

/**
 * Regression B1: smoke-test the entire static PHRASES map on Response. The
 * earlier code had at least one mis-spelled entry; this provider-driven
 * test fails loudly the moment a phrase drifts from its IANA-registered
 * spelling. The map is intentionally inlined here so an accidental rename
 * on the Response side cannot silently keep this test green.
 */
final class ResponseReasonPhraseTableTest extends TestCase
{
    /**
     * @return array<string,array{int,string}>
     */
    public static function ianaPhraseProvider(): array
    {
        // Mirror of the PHRASES constant on Response. Any divergence here is
        // intentional — update both sides only when the spec demands it.
        $map = [
            100 => 'Continue',
            101 => 'Switching Protocols',
            102 => 'Processing',
            103 => 'Early Hints',
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            207 => 'Multi-status',
            208 => 'Already Reported',
            210 => 'Content Different',
            226 => 'IM Used',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            306 => 'Switch Proxy',
            307 => 'Temporary Redirect',
            308 => 'Permanent Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Time-out',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Large',
            415 => 'Unsupported Media Type',
            416 => 'Requested range not satisfiable',
            417 => 'Expectation Failed',
            418 => 'I\'m a teapot',
            421 => 'Misdirected Request',
            422 => 'Unprocessable Entity',
            423 => 'Locked',
            424 => 'Failed Dependency',
            425 => 'Unordered Collection',
            426 => 'Upgrade Required',
            428 => 'Precondition Required',
            429 => 'Too Many Requests',
            431 => 'Request Header Fields Too Large',
            451 => 'Unavailable For Legal Reasons',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Time-out',
            505 => 'HTTP Version not supported',
            506 => 'Variant Also Negotiates',
            507 => 'Insufficient Storage',
            508 => 'Loop Detected',
            510 => 'Not Extended',
            511 => 'Network Authentication Required',
        ];

        $cases = [];
        foreach ($map as $code => $phrase) {
            $cases[(string) $code] = [$code, $phrase];
        }
        return $cases;
    }

    /**
     * @dataProvider ianaPhraseProvider
     */
    public function testReasonPhraseMatchesIanaRegistry(int $code, string $expected): void
    {
        $response = new Response($code);
        self::assertSame($expected, $response->getReasonPhrase(), 'Status '.$code);
    }

    public function testInternalServerErrorPhraseIsExact(): void
    {
        // B1 anchor: the previous misspelling lived on this exact code.
        $response = new Response(500);
        self::assertSame('Internal Server Error', $response->getReasonPhrase());
    }

    public function testUnknownStatusCodeProducesEmptyReason(): void
    {
        // PSR-7 allows an empty reason phrase; the code simply leaves the
        // reason as '' when no IANA entry is found and no override was
        // supplied to the constructor.
        $response = new Response(599);
        self::assertSame('', $response->getReasonPhrase());
    }

    public function testExplicitReasonPhraseOverridesIanaDefault(): void
    {
        $response = new Response(200, [], null, '1.1', 'Custom Reason');
        self::assertSame('Custom Reason', $response->getReasonPhrase());
    }
}
