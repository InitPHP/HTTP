<?php
/**
 * MessageTrait.php
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
use Psr\Http\Message\StreamInterface;

use function array_merge;
use function implode;
use function is_array;
use function is_numeric;
use function is_string;
use function preg_match;
use function strtolower;
use function trim;

/**
 * Shared PSR-7 MessageInterface plumbing reused by Request, Response and
 * ServerRequest. Owns the protocol version, the case-insensitive header bag
 * and the body Stream, and ships both immutable PSR-7 `with*()` operations
 * and in-place `set*()` mutators used internally during construction.
 */
trait MessageTrait
{

    protected array $headers = [];

    protected array $headerNames = [];

    protected string $protocol = '1.1';

    protected StreamInterface $stream;

    /**
     * Return the HTTP protocol version (e.g. "1.1", "2.0").
     *
     * @return string
     */
    public function getProtocolVersion(): string
    {
        return $this->protocol;
    }

    /**
     * Replace the protocol version (in-place). No-ops when the value is
     * already current to avoid spurious mutations.
     *
     * @param  string $version
     * @return $this
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
     * Return a clone of the message with the protocol version replaced.
     *
     * @param  string $version
     * @return static
     */
    public function withProtocolVersion($version): self
    {
        return (clone $this)->setProtocolVersion($version);
    }

    /**
     * Return all headers as an associative array of name => list-of-values.
     * Names are returned in the original case supplied by the caller.
     *
     * @return array<string,string[]>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * True when a header with the given (case-insensitive) name exists.
     *
     * @param  string $name
     * @return bool
     */
    public function hasHeader($name): bool
    {
        return isset($this->headerNames[strtolower($name)]);
    }

    /**
     * Return all values for the given header (case-insensitive lookup), or
     * an empty array when the header is not present.
     *
     * @param  string $name
     * @return string[]
     */
    public function getHeader($name)
    {
        $lowercase = strtolower($name);
        $name = $this->headerNames[$lowercase] ?? $name;
        return $this->headers[$name] ?? [];
    }

    /**
     * Return the header values joined by ", " — the canonical "header line"
     * representation per RFC 7230.
     *
     * @param  string $name
     * @return string
     */
    public function getHeaderLine($name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    /**
     * Replace (or create) a header (in-place). Existing values for the same
     * name are dropped; the supplied value(s) are validated against RFC 7230
     * before being stored.
     *
     * @param  string          $name
     * @param  string|string[] $value
     * @return $this
     * @throws \InvalidArgumentException When the header name or any value is invalid.
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
     * Return a clone of the message with the given header replaced.
     *
     * @param  string          $name
     * @param  string|string[] $value
     * @return static
     * @throws \InvalidArgumentException When the header name or any value is invalid.
     */
    public function withHeader($name, $value): self
    {
        return (clone $this)->setHeader($name, $value);
    }

    /**
     * Append the value(s) to an existing header (in-place), preserving any
     * already-stored values. Creates the header when absent.
     *
     * @param  string          $name
     * @param  string|string[] $value
     * @return $this
     * @throws \InvalidArgumentException When the header name is empty or values are invalid.
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
     * Return a clone of the message with the value(s) appended to the
     * existing header.
     *
     * @param  string          $name
     * @param  string|string[] $value
     * @return static
     * @throws \InvalidArgumentException When the header name is empty or values are invalid.
     */
    public function withAddedHeader($name, $value): self
    {
        return (clone $this)->addedHeader($name, $value);
    }

    /**
     * Remove a header (in-place). No-op when the header does not exist.
     *
     * @param  string $name
     * @return $this
     */
    public function outHeader($name): self
    {
        $lowercase = strtolower($name);
        if (!isset($this->headerNames[$lowercase])) {
            return $this;
        }

        $original = $this->headerNames[$lowercase];
        unset($this->headers[$original], $this->headerNames[$lowercase]);

        return $this;
    }

    /**
     * Return a clone of the message without the given header.
     *
     * @param  string $name
     * @return static
     */
    public function withoutHeader($name): self
    {
        return (clone $this)->outHeader($name);
    }

    /**
     * Return the message body as a PSR-7 StreamInterface. A fresh, empty
     * stream is created on demand if no body has been assigned yet.
     *
     * @return StreamInterface
     */
    public function getBody(): StreamInterface
    {
        if(!isset($this->stream)){
            $this->stream = new Stream('');
        }
        return $this->stream;
    }

    /**
     * Replace the message body (in-place). Assigning the same stream twice
     * short-circuits to avoid trivial mutations.
     *
     * @param  StreamInterface $body
     * @return $this
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
     * Return a clone of the message with the body replaced.
     *
     * @param  StreamInterface $body
     * @return static
     */
    public function withBody(StreamInterface $body): self
    {
        return (clone $this)->setBody($body);
    }

    /**
     * Bulk-load headers from an associative array, validating every value
     * and folding additional values into existing headers (case-insensitive
     * key collision).
     *
     * @param  array<string,string|string[]> $headers
     * @return $this
     * @throws \InvalidArgumentException When any header name or value is invalid.
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
     * True when the body is known to contain zero bytes. A size of null
     * (pipes, sockets, on-the-fly responses) is treated as indeterminate —
     * both {@see MessageTrait::isEmpty()} and
     * {@see MessageTrait::isNotEmpty()} return false in that case so
     * callers can branch defensively.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        $size = $this->getBody()->getSize();
        return $size !== null && $size < 1;
    }

    /**
     * Counterpart of {@see MessageTrait::isEmpty()}: only true when the
     * body size is known and strictly positive.
     *
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        $size = $this->getBody()->getSize();
        return $size !== null && $size > 0;
    }

    /**
     * Validate the header name against RFC 7230 token rules and coerce the
     * value(s) into a list of trimmed strings, rejecting anything that
     * contains control characters or non-tchar bytes.
     *
     * @param  string                       $header
     * @param  string|int|float|array<int,string|int|float> $values
     * @return string[]
     * @throws \InvalidArgumentException When the name or any value violates RFC 7230.
     */
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
