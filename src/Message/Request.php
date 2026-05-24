<?php
/**
 * Request.php
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

use \InitPHP\HTTP\Message\Traits\{MessageTrait, RequestTrait};
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * PSR-7 RequestInterface implementation backed by {@see MessageTrait} and
 * {@see RequestTrait}. Carries the HTTP method, request target, URI, headers
 * and body; immutability of `with*()` returns is guaranteed by the deep
 * clone in {@see Request::__clone()}.
 */
class Request implements RequestInterface
{

    use MessageTrait, RequestTrait;

    /**
     * Clone the body and URI deeply so callers using `with*()` cannot
     * mutate the original message via the returned copy. Without this the
     * default shallow clone leaves both instances pointing at the same
     * StreamInterface and UriInterface, violating PSR-7 immutability.
     *
     * @return void
     */
    public function __clone()
    {
        if (isset($this->stream)) {
            $this->stream = clone $this->stream;
        }
        if (isset($this->uri)) {
            $this->uri = clone $this->uri;
        }
    }

    /**
     * Build a new request.
     *
     * @param  string                                                 $method  HTTP method (case preserved as supplied).
     * @param  string|UriInterface                                    $uri     Target URI as a string or PSR-7 UriInterface.
     * @param  array<string,string|string[]>                          $headers Header name => value(s).
     * @param  string|resource|\Psr\Http\Message\StreamInterface|null $body    Request body in any form accepted by {@see Stream}.
     * @param  string                                                 $version HTTP protocol version (e.g. "1.1", "2.0").
     * @throws \InvalidArgumentException When $uri is a malformed URI string, or any supplied header is invalid.
     */
    public function __construct(string $method, $uri, array $headers = [], $body = null, string $version = '1.1')
    {
        if (!($uri instanceof UriInterface)) {
            $uri = new Uri($uri);
        }
        $this->setUpConstruct($method, $uri, $body, $headers, $version);
    }

}
