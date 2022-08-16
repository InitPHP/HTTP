<?php
/**
 * MessageTrait.php
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

use Psr\Http\Message\StreamInterface;

use function strtolower;
use function implode;
use function is_string;
use function is_numeric;
use function is_array;
use function array_merge;
use function preg_match;
use function trim;

trait MessageTrait
{

    protected array $headers = [];

    protected array $headerNames = [];

    protected string $protocol = '1.1';

    protected StreamInterface $stream;

    public function getProtocolVersion(): string
    {
        return $this->protocol;
    }

    public function withProtocolVersion($version): self
    {
        if($this->protocol === $version){
            return $this;
        }
        $clone = clone $this;
        $clone->protocol = $version;
        return $clone;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader($name): bool
    {
        return isset($this->headerNames[strtolower($name)]);
    }

    public function getHeader($name)
    {
        $lowercase = strtolower($name);
        $name = $this->headerNames[$lowercase] ?? $name;
        return $this->headers[$name] ?? [];
    }

    public function getHeaderLine($name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader($name, $value): self
    {
        $value = $this->validateAndTrimHeader($name, $value);
        $lowercase = strtolower($name);
        $clone = clone $this;
        if(isset($clone->headerNames[$lowercase])){
            unset($clone->headerNames[$lowercase]);
        }
        $clone->headerNames[$lowercase] = $name;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function withAddedHeader($name, $value): self
    {
        if(!is_string($name) || empty($name)){
            throw new \InvalidArgumentException('Header name must be an RFC 7230 compatible string.');
        }
        $clone = clone $this;
        $clone->setHeaders([$name => $value]);
        return $clone;
    }

    public function withoutHeader($name): self
    {
        $lowercase = strtolower($name);
        if(!isset($this->headerNames[$lowercase])){
            return $this;
        }
        $name = $this->headerNames[$lowercase];
        $clone = clone $this;
        unset($clone->headers[$name], $clone->headerNames[$lowercase]);
        return $clone;
    }

    public function getBody(): StreamInterface
    {
        if(!isset($this->stream)){
            $this->stream = new Stream('');
        }
        return $this->stream;
    }

    public function withBody(StreamInterface $body): self
    {
        if(isset($this->stream) && $body === $this->stream){
            return $this;
        }
        $clone = clone $this;
        $clone->stream = $body;
        return $clone;
    }

    protected function setHeaders(array $headers): void
    {
        foreach ($headers as $key => $value) {
            $name = (string)$key;
            $value = $this->validateAndTrimHeader($name, $value);
            $lowercase = strtolower($name);
            if(isset($this->headerNames[$lowercase])){
                $header = $this->headerNames[$lowercase];
                $this->headers[$header] = array_merge($this->headers[$header], $value);
                continue;
            }
            $this->headerNames[$lowercase] = $name;
            $this->headers[$name] = $value;
        }
    }

    protected function validateAndTrimHeader($header, $values): array
    {
        if(!is_string($header) || (bool)preg_match("@^[!#$%&'*+.^_`|~0-9A-Za-z-]+$@", $header) === FALSE){
            throw new \InvalidArgumentException('Header name must be an RFC 7230 compatible string.');
        }
        if(!is_array($values)){
            if((!is_numeric($values) && !is_string($values)) || ((bool)preg_match("@^[ \t\x21-\x7E\x80-\xFF]*$@", (string)$values)) === FALSE){
                throw new \InvalidArgumentException('Header values must be RFC 7230 compatible strings.');
            }
            return [trim((string)$values, " \t")];
        }
        if(empty($values)){
            throw new \InvalidArgumentException('Header values must be a string or an array of strings, empty array given.');
        }
        $res = [];
        foreach ($values as $value) {
            if((!is_numeric($value) && !is_string($value)) || (bool)preg_match("@^[ \t\x21-\x7E\x80-\xFF]*$@", (string)$value) === FALSE){
                throw new \InvalidArgumentException('Header values must be RFC 7230 compatible strings.');
            }
            $res[] = trim((string)$value, " \t");
        }
        return $res;
    }

}
