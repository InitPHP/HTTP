<?php
/**
 * RequestTrait.php
 *
 * This file is part of InitPHP HTTP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    2.0
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

trait RequestTrait
{

    protected string $method;

    protected UriInterface $uri;

    protected string $requestTarget;

    /**
     * @inheritDoc
     */
    public function isGet(): bool
    {
        return $this->getMethod() === 'GET';
    }

    /**
     * @inheritDoc
     */
    public function isPost(): bool
    {
        return $this->getMethod() === 'POST';
    }

    /**
     * @inheritDoc
     */
    public function isPut(): bool
    {
        return $this->getMethod() === 'PUT';
    }

    /**
     * @inheritDoc
     */
    public function isDelete(): bool
    {
        return $this->getMethod() === 'DELETE';
    }

    /**
     * @inheritDoc
     */
    public function isHead(): bool
    {
        return $this->getMethod() === 'HEAD';
    }

    /**
     * @inheritDoc
     */
    public function isPatch(): bool
    {
        return $this->getMethod() === 'PATCH';
    }

    /**
     * @inheritDoc
     */
    public function isMethod(string ...$method): bool
    {
        return in_array($this->getMethod(), $method);
    }

    /**
     * @inheritDoc
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
     * @inheritDoc
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
     * @inheritDoc
     */
    public function withRequestTarget($requestTarget): self
    {
        return (clone $this)->setRequestTarget($requestTarget);
    }

    /**
     * @inheritDoc
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @inheritDoc
     */
    public function setMethod($method): self
    {
        if(!is_string($method)){
            throw new \InvalidArgumentException('Method must be a string.');
        }
        $this->method = strtoupper($method);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withMethod($method): self
    {
        return (clone $this)->setMethod($method);
    }

    /**
     * @inheritDoc
     */
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /**
     * @inheritDoc
     */
    public function setUri(UriInterface $uri, bool $preserveHost = false): self
    {
        if($uri === $this->uri){
            return $this;
        }
        $this->uri = $uri;
        if (!$preserveHost || !$this->hasHeader('Host')) {
            $this->updateHostFormUri();
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withUri(UriInterface $uri, $preserveHost = false): self
    {
        return (clone $this)->setUri($uri, $preserveHost);
    }

    protected function updateHostFormUri()
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

    protected function setUpConstruct($method, $uri, $body, $headers, $version)
    {
        $this->method = $method;
        $this->uri = $uri;
        $this->setHeaders($headers);
        $this->protocol = $version;
        if(!$this->hasHeader('Host')){
            $this->updateHostFormUri();
        }
        if($body instanceof StreamInterface){
            $this->stream = $body;
        }elseif(!empty($body)){
            $this->stream = new Stream($body);
        }
    }

}
