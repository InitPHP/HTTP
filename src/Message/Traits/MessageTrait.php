<?php
/**
 * MessageTrait.php
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
use Psr\Http\Message\StreamInterface;

use function array_merge;
use function implode;
use function is_array;
use function is_numeric;
use function is_string;
use function preg_match;
use function strtolower;
use function trim;

trait MessageTrait
{

    protected array $headers = [];

    protected array $headerNames = [];

    protected string $protocol = '1.1';

    protected StreamInterface $stream;

    /**
     * @inheritDoc
     */
    public function getProtocolVersion(): string
    {
        return $this->protocol;
    }

    /**
     * @inheritDoc
     */
    public function setProtocolVersion(string $version): self
    {
        if ($this->protocol === $version) {
            return $this;
        }
        $this->protocol = $version;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withProtocolVersion($version): self
    {
        return (clone $this)->setProtocolVersion($version);
    }

    /**
     * @inheritDoc
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @inheritDoc
     */
    public function hasHeader($name): bool
    {
        return isset($this->headerNames[strtolower($name)]);
    }

    /**
     * @inheritDoc
     */
    public function getHeader($name)
    {
        $lowercase = strtolower($name);
        $name = $this->headerNames[$lowercase] ?? $name;
        return $this->headers[$name] ?? [];
    }

    /**
     * @inheritDoc
     */
    public function getHeaderLine($name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    /**
     * @inheritDoc
     */
    public function setHeader($name, $value): self
    {
        $value = $this->validateAndTrimHeader($name, $value);
        $lowercase = strtolower($name);
        $this->headerNames[$lowercase] = $name;
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withHeader($name, $value): self
    {
        return (clone $this)->setHeader($name, $value);
    }

    /**
     * @inheritDoc
     */
    public function addedHeader($name, $value): self
    {
        if (!is_string($name) || empty($name)) {
            throw new \InvalidArgumentException('Header name must be an RFC 7230 compatible string.');
        }
        $this->setHeaders([$name => $value]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withAddedHeader($name, $value): self
    {
        return (clone $this)->addedHeader($name, $value);
    }

    /**
     * @inheritDoc
     */
    public function outHeader($name): self
    {
        $lowercase = strtolower($name);
        if (!isset($this->headerNames[$lowercase])) {
            return $this;
        }

        $name = $this->headerNames[$lowercase];
        unset($this->headerNames[$name], $this->headerNames[$lowercase]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withoutHeader($name): self
    {
        return (clone $this)->outHeader($name);
    }

    /**
     * @inheritDoc
     */
    public function getBody(): StreamInterface
    {
        if(!isset($this->stream)){
            $this->stream = new Stream('');
        }
        return $this->stream;
    }

    /**
     * @inheritDoc
     */
    public function setBody(StreamInterface $body): self
    {
        if (isset($this->stream) && $body === $this->stream) {
            return $this;
        }
        $this->stream = $body;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withBody(StreamInterface $body): self
    {
        return (clone $this)->setBody($body);
    }

    /**
     * @inheritDoc
     */
    public function setHeaders(array $headers): self
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

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isEmpty(): bool
    {
        return $this->getBody()->getSize() < 1;
    }

    /**
     * @inheritDoc
     */
    public function isNotEmpty(): bool
    {
        return $this->getBody()->getSize() > 0;
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
