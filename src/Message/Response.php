<?php
/**
 * Response.php
 *
 * This file is part of InitPHP HTTP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\HTTP\Message;

use \InvalidArgumentException;
use \InitPHP\HTTP\Message\Traits\MessageTrait;
use \Psr\Http\Message\{ResponseInterface, StreamInterface};

use function in_array;
use function is_numeric;
use function is_resource;
use function is_scalar;
use function is_string;
use function json_encode;

/**
 * PSR-7 ResponseInterface implementation backed by {@see MessageTrait}.
 * Carries the response status code, reason phrase, headers and body;
 * immutability of `with*()` returns is guaranteed by the deep clone in
 * {@see Response::__clone()}. Ships with two convenience producers,
 * {@see Response::json()} and {@see Response::redirect()}, on top of the
 * standard PSR-7 surface.
 */
class Response implements ResponseInterface
{

    use MessageTrait;

    protected const PHRASES = [
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

    protected int $statusCode;
    protected string $reasonPhrase;


    /**
     * Deep-clone the body Stream so callers using `with*()` cannot mutate
     * the original response via `$cloned->getBody()->write(...)`. Without
     * this the default shallow clone leaves both instances pointing at the
     * same underlying resource handle.
     *
     * @return void
     */
    public function __clone()
    {
        if (isset($this->stream)) {
            $this->stream = clone $this->stream;
        }
    }

    /**
     * Build a new response.
     *
     * @param  int                                          $status  HTTP status code (100..599).
     * @param  array<string,string|string[]>                $headers Header name => value(s).
     * @param  StreamInterface|string|resource|null         $body    Response body in any form accepted by {@see Stream}.
     * @param  string                                       $version HTTP protocol version; one of "1.0", "1.1", "2", "2.0", "3", "3.0".
     * @param  string|null                                  $reason  Reason phrase; defaults to the IANA phrase for $status when null.
     * @throws InvalidArgumentException When $version is not one of the supported HTTP version strings.
     */
    public function __construct(int $status = 200, array $headers = [], $body = null, string $version = '1.1', ?string $reason = null)
    {
        if (!in_array($version, ['1.0', '1.1', '2', '2.0', '3', '3.0'], true)) {
            throw new \InvalidArgumentException(
                'Supported HTTP versions are: "1.0", "1.1", "2", "2.0", "3" or "3.0".'
            );
        }
        $this->setStream($body);
        $this->statusCode = $status;
        $this->setHeaders($headers);
        if($reason === null && isset(self::PHRASES[$this->statusCode])){
            $this->reasonPhrase = self::PHRASES[$this->statusCode];
        }else{
            $this->reasonPhrase = $reason ?? '';
        }
        $this->protocol = $version;
    }

    /**
     * Assign the body from a flexible input shape: StreamInterface and
     * resource values are wrapped/stored verbatim; scalar values are
     * stringified through a fresh {@see Stream}; null is a no-op.
     *
     * @param  StreamInterface|resource|scalar|null $body
     * @return $this
     */
    public function setStream($body): self
    {
        if($body === null){
            return $this;
        }
        if($body instanceof StreamInterface){
            $this->stream = $body;
            return $this;
        }
        if(is_resource($body)){
            $this->stream = new Stream($body);
            return $this;
        }
        if(is_scalar($body)){
            $this->stream = new Stream((string)$body);
            return $this;
        }
        return $this;
    }

    /**
     * Return the HTTP status code.
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Replace the status code (and optionally the reason phrase) in-place.
     * When $reasonPhrase is empty and an IANA phrase is known for $code,
     * the IANA phrase is used.
     *
     * @param  int    $code         Status code in the range 100..599.
     * @param  string $reasonPhrase Optional reason phrase override.
     * @return $this
     * @throws InvalidArgumentException When $code is outside the 100..599 range.
     */
    public function setStatusCode(int $code, string $reasonPhrase = ''): self
    {
        if(!is_numeric($code)){
            throw new InvalidArgumentException('Status code has to be an integer');
        }
        $code = (int)$code;
        if($code < 100 || $code > 599){
            throw new InvalidArgumentException('Status code has to be an integer between 100 and 599. A status code of '.$code.' was given.');
        }
        $this->statusCode = $code;
        if (empty($reasonPhrase) && isset(self::PHRASES[$code])) {
            $reasonPhrase = self::PHRASES[$code];
        }
        $this->reasonPhrase = $reasonPhrase;

        return $this;
    }

    /**
     * Return a clone of the response with the status code (and optional
     * reason phrase) replaced.
     *
     * @param  int    $code
     * @param  string $reasonPhrase
     * @return static
     * @throws InvalidArgumentException When $code is outside the 100..599 range.
     */
    public function withStatus($code, $reasonPhrase = ''): self
    {
        return (clone $this)->setStatusCode($code, $reasonPhrase);
    }

    /**
     * Return the reason phrase associated with the current status code.
     *
     * @return string
     */
    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    /**
     * Return a JSON-encoded copy of this response with the appropriate
     * Content-Type and status. Encoding errors raise InvalidArgumentException
     * instead of silently producing a non-JSON body.
     *
     * @param  array<string,mixed>|list<mixed> $data
     * @param  int                             $status HTTP status code for the new response.
     * @param  int                             $flags  Additional json_encode() flags OR'd onto JSON_THROW_ON_ERROR.
     * @return static
     * @throws InvalidArgumentException When $data cannot be encoded as JSON, or $status is out of range.
     */
    public function json(array $data = [], int $status = 200, int $flags = 0): self
    {
        try {
            $encoded = json_encode($data, JSON_THROW_ON_ERROR | $flags);
        } catch (\JsonException $e) {
            throw new InvalidArgumentException('Cannot encode response payload as JSON: ' . $e->getMessage(), 0, $e);
        }

        return (clone $this)
            ->setHeader('Content-Type', 'application/json; charset=utf-8')
            ->setBody(new Stream($encoded, null))
            ->setStatusCode($status);
    }

    /**
     * Return a redirect copy of this response.
     *
     * The Location header is always set so non-browser clients (crawlers,
     * HTTP libraries, monitoring) can follow the redirect; the Refresh
     * header is added on top when a non-zero $second is supplied so
     * browsers honour the delay.
     *
     * @param  string|\Psr\Http\Message\UriInterface $uri    Target location, as a string or PSR-7 URI.
     * @param  int                                   $status HTTP status code (typically 301/302/303/307/308).
     * @param  int                                   $second Delay in seconds before browsers follow; 0 omits the Refresh header.
     * @return static
     * @throws InvalidArgumentException When $uri is not a string or UriInterface, or $status is out of range.
     */
    public function redirect($uri, int $status = 302, int $second = 0): self
    {
        if (is_string($uri)) {
            $uri = new Uri($uri);
        }
        if (!($uri instanceof \Psr\Http\Message\UriInterface)) {
            throw new InvalidArgumentException('URI is not valid.');
        }

        $location = (string) $uri;
        $with = (clone $this)
            ->setStatusCode($status)
            ->setHeader('Location', $location);

        if ($second > 0) {
            $with->setHeader('Refresh', $second . '; url=' . $location);
        }

        return $with;
    }

}
