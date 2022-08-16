<?php
/**
 * RequestTrait.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    http://initphp.github.com/license.txt  MIT
 * @version    1.0.3
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\HTTP;

use \Psr\Http\Message\{StreamInterface, UriInterface};

use function preg_match;
use function is_string;
use function strtoupper;

trait RequestTrait
{

    protected string $method;

    protected UriInterface $uri;

    protected string $requestTarget;

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

    public function withRequestTarget($requestTarget): self
    {
        if(preg_match("#\s#", $requestTarget)){
            throw new \InvalidArgumentException('Invalid request target provided; cannot contain whitespace.');
        }
        $clone = clone $this;
        $clone->requestTarget = $requestTarget;
        return $clone;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod($method): self
    {
        if(!is_string($method)){
            throw new \InvalidArgumentException('Method must be a string.');
        }
        $clone = clone $this;
        $clone->method = strtoupper($method);
        return $clone;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, $preserveHost = false): self
    {
        if($uri === $this->uri){
            return $this;
        }
        $clone = clone $this;
        $clone->uri = $uri;
        if(!$preserveHost || !$this->hasHeader('Host')){
            $this->updateHostFormUri();
        }
        return $clone;
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
