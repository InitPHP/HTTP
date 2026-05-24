<?php
/**
 * RequestTrait.php
 *
 * This file is part of InitPHP HTTP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\HTTP\Message\Traits;

use InitPHP\HTTP\Message\Stream;
use \Psr\Http\Message\{StreamInterface, UriInterface};

use function in_array;
use function is_string;
use function preg_match;
use function strtoupper;
use function array_map;

/**
 * Shared request-shaped behaviour for the concrete Request and ServerRequest
 * classes. Implements PSR-7 RequestInterface accessors (method, URI, request
 * target) on top of {@see MessageTrait}, plus convenience predicates such as
 * {@see RequestTrait::isGet()} that wrap a case-insensitive method check.
 */
trait RequestTrait
{

    protected string $method;

    protected UriInterface $uri;

    protected string $requestTarget;

    /**
     * True when the request method is GET (case-insensitive comparison).
     *
     * @return bool
     */
    public function isGet(): bool
    {
        return strtoupper($this->getMethod()) === 'GET';
    }

    /**
     * True when the request method is POST (case-insensitive comparison).
     *
     * @return bool
     */
    public function isPost(): bool
    {
        return strtoupper($this->getMethod()) === 'POST';
    }

    /**
     * True when the request method is PUT (case-insensitive comparison).
     *
     * @return bool
     */
    public function isPut(): bool
    {
        return strtoupper($this->getMethod()) === 'PUT';
    }

    /**
     * True when the request method is DELETE (case-insensitive comparison).
     *
     * @return bool
     */
    public function isDelete(): bool
    {
        return strtoupper($this->getMethod()) === 'DELETE';
    }

    /**
     * True when the request method is HEAD (case-insensitive comparison).
     *
     * @return bool
     */
    public function isHead(): bool
    {
        return strtoupper($this->getMethod()) === 'HEAD';
    }

    /**
     * True when the request method is PATCH (case-insensitive comparison).
     *
     * @return bool
     */
    public function isPatch(): bool
    {
        return strtoupper($this->getMethod()) === 'PATCH';
    }

    /**
     * True when the current request method matches one of the supplied
     * candidates. Comparison is case-insensitive on both sides.
     *
     * @param  string ...$method Candidate HTTP methods to test against.
     * @return bool
     */
    public function isMethod(string ...$method): bool
    {
        return in_array(strtoupper($this->getMethod()), array_map('strtoupper', $method), true);
    }

    /**
     * Return the request target as defined by PSR-7: an explicit override set
     * via {@see RequestTrait::setRequestTarget()} takes precedence, otherwise
     * the URI's path (defaulting to "/") with the query string appended.
     *
     * @return string
     */
    public function getRequestTarget(): string
    {
        if(isset($this->requestTarget)){
            return $this->requestTarget;
        }
        if(($target = $this->uri->getPath()) === ''){
            $target = '/';
        }
        if(($query = $this->uri->getQuery()) !== ''){
            $target .= '?' . $query;
        }

        return $target;
    }

    /**
     * Replace the request target (in-place). The target must not contain
     * whitespace per PSR-7.
     *
     * @param  string $requestTarget
     * @return $this
     * @throws \InvalidArgumentException When the request target contains whitespace.
     */
    public function setRequestTarget($requestTarget): self
    {
        if(preg_match("#\s#", $requestTarget)){
            throw new \InvalidArgumentException('Invalid request target provided; cannot contain whitespace.');
        }
        $this->requestTarget = $requestTarget;

        return $this;
    }

    /**
     * Return a clone of the message with the request target replaced.
     *
     * @param  string $requestTarget
     * @return static
     * @throws \InvalidArgumentException When the request target contains whitespace.
     */
    public function withRequestTarget($requestTarget): self
    {
        return (clone $this)->setRequestTarget($requestTarget);
    }

    /**
     * Return the HTTP method string as supplied by the caller (case preserved).
     *
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Replace the HTTP method (in-place).
     *
     * @param  string $method
     * @return $this
     * @throws \InvalidArgumentException When $method is not a string.
     */
    public function setMethod($method): self
    {
        if (!is_string($method)) {
            throw new \InvalidArgumentException('Method must be a string.');
        }
        $this->method = $method;
        return $this;
    }

    /**
     * Return a clone of the message with the HTTP method replaced.
     *
     * @param  string $method
     * @return static
     * @throws \InvalidArgumentException When $method is not a string.
     */
    public function withMethod($method): self
    {
        return (clone $this)->setMethod($method);
    }

    /**
     * Return the PSR-7 URI currently associated with this request.
     *
     * @return UriInterface
     */
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /**
     * Replace the URI (in-place). The Host header is synchronised to the new
     * URI unless $preserveHost is true AND a Host header already exists, per
     * PSR-7 RequestInterface::withUri() semantics.
     *
     * @param  UriInterface $uri
     * @param  bool         $preserveHost
     * @return $this
     */
    public function setUri(UriInterface $uri, bool $preserveHost = false): self
    {
        if($uri === $this->uri){
            return $this;
        }
        $this->uri = $uri;
        if (!$preserveHost || !$this->hasHeader('Host')) {
            $this->updateHostFromUri();
        }

        return $this;
    }

    /**
     * Return a clone of the message with the URI replaced, applying the same
     * Host-synchronisation rule as {@see RequestTrait::setUri()}.
     *
     * @param  UriInterface $uri
     * @param  bool         $preserveHost
     * @return static
     */
    public function withUri(UriInterface $uri, $preserveHost = false): self
    {
        return (clone $this)->setUri($uri, $preserveHost);
    }

    /**
     * Synchronise the Host header from the current URI. The header is
     * prepended to the header collection so it appears first when emitted,
     * matching the canonical RFC 7230 order.
     *
     * @return void
     */
    protected function updateHostFromUri()
    {
        if(($host = $this->uri->getHost()) === ''){
            return;
        }
        if(($port = $this->uri->getPort()) !== null){
            $host .= ':' . $port;
        }
        if(isset($this->headerNames['host'])){
            $header = $this->headerNames['host'];
        }else{
            $this->headerNames['host'] = $header = 'Host';
        }
        $this->headers = [$header => [$host]] + $this->headers;
    }

    /**
     * Shared constructor body for Request / ServerRequest: assign method,
     * URI, headers, protocol version and body in one pass, ensuring the
     * Host header is derived from the URI when not supplied explicitly.
     *
     * @param  string                                                $method
     * @param  UriInterface                                          $uri
     * @param  string|resource|StreamInterface|null                  $body
     * @param  array<string,string|string[]>                         $headers
     * @param  string                                                $version
     * @return void
     */
    protected function setUpConstruct($method, $uri, $body, $headers, $version)
    {
        $this->method = $method;
        $this->uri = $uri;
        $this->setHeaders($headers);
        $this->protocol = $version;
        if(!$this->hasHeader('Host')){
            $this->updateHostFromUri();
        }
        if($body instanceof StreamInterface){
            $this->stream = $body;
        }elseif(!empty($body)){
            $this->stream = new Stream($body);
        }
    }

}
