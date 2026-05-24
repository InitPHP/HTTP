<?php
/**
 * ServerRequest.php
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
use \Psr\Http\Message\{ServerRequestInterface, UploadedFileInterface, UriInterface};

use const PHP_SAPI;

use function array_keys;
use function array_merge;
use function file_get_contents;
use function function_exists;
use function in_array;
use function is_array;
use function is_object;
use function json_decode;
use function parse_str;
use function str_replace;
use function strpos;
use function strtolower;
use function substr;
use function ucwords;

/**
 * PSR-7 ServerRequestInterface implementation backed by {@see MessageTrait}
 * and {@see RequestTrait}. Adds the server-side state PSR-7 attaches to an
 * inbound request — server params, cookies, query, parsed body, uploaded
 * files and arbitrary attributes — plus a stateless
 * {@see ServerRequest::createFromGlobals()} bootstrapper safe to use under
 * persistent runtimes (Swoole, RoadRunner, Octane, FrankenPHP).
 */
class ServerRequest implements ServerRequestInterface
{

    use MessageTrait, RequestTrait;

    protected array $serverParams = [];

    protected array $cookieParams = [];

    protected array $queryParams = [];

    /** @var null|object|array */
    protected $parsedBody = null;

    protected array $attributes = [];

    /** @var UploadedFileInterface[] */
    protected array $uploadedFiles = [];


    /**
     * Deep-clone the body Stream and URI so callers using `with*()` cannot
     * mutate the original message via the returned copy. The uploadedFiles
     * and attributes arrays are raw PHP arrays — copy-on-write already
     * shields them.
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
     * Build a new server request.
     *
     * @param  string                                                 $method       HTTP method (case preserved as supplied).
     * @param  string|UriInterface                                    $uri          Target URI as a string or PSR-7 UriInterface.
     * @param  array<string,string|string[]>                          $headers      Header name => value(s).
     * @param  string|resource|\Psr\Http\Message\StreamInterface|null $body         Request body in any form accepted by {@see Stream}.
     * @param  string                                                 $version      HTTP protocol version (e.g. "1.1", "2.0").
     * @param  array<string,mixed>                                    $serverParams Snapshot of `$_SERVER`-style entries.
     * @throws \InvalidArgumentException When $uri is a malformed URI string, or any supplied header is invalid.
     */
    public function __construct(string $method, $uri, array $headers = [], $body = null, string $version = '1.1', array $serverParams = [])
    {
        $this->serverParams = $serverParams;
        if(!($uri instanceof UriInterface)){
            $uri = new Uri($uri);
        }
        $this->setUpConstruct($method, $uri, $body, $headers, $version);
    }

    /**
     * Hydrate a ServerRequest from the PHP superglobals of the current process.
     *
     * Unlike legacy implementations this method is **stateless**: every call
     * returns a fresh instance computed from the supplied (or default)
     * superglobal snapshots, making it safe to use under Swoole, RoadRunner,
     * Octane, FrankenPHP and similar persistent runtimes.
     *
     * Body parsing is Content-Type-aware:
     *   - application/json                  -> json_decode(..., true)
     *   - application/x-www-form-urlencoded -> parse_str(...)
     *   - anything else                     -> parsedBody left as null and
     *     the caller can rely on $_POST/$_FILES if appropriate.
     *
     * Header collection falls back to parsing HTTP_* keys from $_SERVER when
     * apache_request_headers() is unavailable (nginx + php-fpm, FrankenPHP).
     *
     * @param  array<string,mixed>|null $server  Defaults to $_SERVER.
     * @param  array<string,mixed>|null $get     Defaults to $_GET.
     * @param  array<string,mixed>|null $post    Defaults to $_POST.
     * @param  array<string,mixed>|null $cookies Defaults to $_COOKIE.
     * @param  array<string,mixed>|null $files   Defaults to $_FILES.
     * @return self
     * @throws \InvalidArgumentException When $files contains malformed entries that {@see normalizeFiles()} rejects.
     */
    public static function createFromGlobals(
        ?array $server = null,
        ?array $get = null,
        ?array $post = null,
        ?array $cookies = null,
        ?array $files = null
    ): self {
        $server  = $server  ?? $_SERVER;
        $get     = $get     ?? $_GET;
        $post    = $post    ?? $_POST;
        $cookies = $cookies ?? $_COOKIE;
        $files   = $files   ?? $_FILES;

        $method = isset($server['REQUEST_METHOD']) ? (string) $server['REQUEST_METHOD'] : 'GET';
        if (PHP_SAPI === 'cli' && !isset($server['REQUEST_METHOD'])) {
            $method = 'GET';
        }

        $headers = self::collectRequestHeaders($server);

        $scheme = (!empty($server['HTTPS']) && $server['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = (string) ($server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? 'localhost');
        $port   = isset($server['SERVER_PORT']) ? (int) $server['SERVER_PORT'] : null;
        $path   = (string) ($server['REQUEST_URI'] ?? '/');

        // HTTP_HOST may already include the port; only append SERVER_PORT
        // when the host header omitted one and the port is non-standard.
        $hostHasPort = strpos($host, ':') !== false;
        $standardPort = ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443);
        if (!$hostHasPort && $port !== null && !$standardPort) {
            $host .= ':' . $port;
        }

        $uri = $scheme . '://' . $host . $path;

        $rawBody = '';
        $bodyStream = fopen('php://temp', 'w+b');
        if ($bodyStream !== false) {
            $input = file_get_contents('php://input');
            if (is_string($input) && $input !== '') {
                $rawBody = $input;
                fwrite($bodyStream, $rawBody);
            }
        }
        $stream = $bodyStream !== false ? new Stream($bodyStream) : new Stream('', null);

        $protocol = '1.1';
        if (isset($server['SERVER_PROTOCOL']) && strpos((string) $server['SERVER_PROTOCOL'], 'HTTP/') === 0) {
            $protocol = substr((string) $server['SERVER_PROTOCOL'], 5);
        }

        $request = new self($method, $uri, $headers, $stream, $protocol, $server);
        $request = $request
            ->withCookieParams($cookies)
            ->withQueryParams($get);
        if (!empty($files)) {
            $request = $request->withUploadedFiles($request->normalizeFiles($files));
        }

        $parsedBody = self::parseRequestBody($headers, $server, $rawBody, $post);
        if ($parsedBody !== null) {
            $request = $request->withParsedBody($parsedBody);
        }

        return $request;
    }

    /**
     * Collect inbound request headers, preferring apache_request_headers()
     * when available and falling back to parsing HTTP_* and CONTENT_* keys
     * from the $_SERVER-style snapshot.
     *
     * @param  array<string,mixed> $server
     * @return array<string,string>
     */
    private static function collectRequestHeaders(array $server): array
    {
        if (function_exists('apache_request_headers')) {
            $apache = apache_request_headers();
            if (is_array($apache) && $apache !== []) {
                return $apache;
            }
        }
        $headers = [];
        foreach ($server as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            if (strpos($key, 'HTTP_') === 0) {
                $name = ucwords(strtolower(str_replace('_', '-', substr($key, 5))), '-');
                $headers[$name] = (string) $value;
                continue;
            }
            if (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $name = ucwords(strtolower(str_replace('_', '-', $key)), '-');
                $headers[$name] = (string) $value;
            }
        }
        return $headers;
    }

    /**
     * Decide what to feed into {@see ServerRequest::withParsedBody()} based
     * on the advertised Content-Type. Returns null when the body shouldn't
     * be parsed (multipart, unknown types, or empty bodies for non-form
     * requests).
     *
     * @param  array<string,string> $headers
     * @param  array<string,mixed>  $server
     * @param  string               $rawBody
     * @param  array<string,mixed>  $post
     * @return array<string,mixed>|null
     */
    private static function parseRequestBody(array $headers, array $server, string $rawBody, array $post): ?array
    {
        $contentType = $server['CONTENT_TYPE']
            ?? $headers['Content-Type']
            ?? $headers['content-type']
            ?? '';
        $contentType = strtolower((string) $contentType);

        if ($contentType === '') {
            return null;
        }
        if (strpos($contentType, 'application/json') !== false && $rawBody !== '') {
            $decoded = json_decode($rawBody, true);
            return is_array($decoded) ? $decoded : null;
        }
        if (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
            // PHP already filled $_POST for us when the request reached the
            // FPM/CLI server; fall back to parse_str only if it's empty.
            if (!empty($post)) {
                return $post;
            }
            if ($rawBody !== '') {
                $parsed = [];
                parse_str($rawBody, $parsed);
                return $parsed;
            }
            return null;
        }
        if (strpos($contentType, 'multipart/form-data') !== false && !empty($post)) {
            return $post;
        }
        return null;
    }

    /**
     * Return the $_SERVER-style snapshot supplied at construction time.
     *
     * @return array<string,mixed>
     */
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    /**
     * Return the cookies attached to this request as an associative array.
     *
     * @return array<string,mixed>
     */
    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    /**
     * Replace the cookies array (in-place).
     *
     * @param  array<string,mixed> $cookies
     * @return $this
     */
    public function setCookieParams(array $cookies): self
    {
        $this->cookieParams = $cookies;

        return $this;
    }

    /**
     * Return a clone of the request with the cookies replaced.
     *
     * @param  array<string,mixed> $cookies
     * @return ServerRequest
     */
    public function withCookieParams(array $cookies): ServerRequest
    {
        return (clone $this)->setCookieParams($cookies);
    }

    /**
     * Return the deserialised query string parameters.
     *
     * @return array<string,mixed>
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * Replace the query parameter array (in-place).
     *
     * @param  array<string,mixed> $query
     * @return $this
     */
    public function setQueryParams(array $query): self
    {
        $this->queryParams = $query;

        return $this;
    }

    /**
     * Return a clone of the request with the query parameters replaced.
     *
     * @param  array<string,mixed> $query
     * @return ServerRequest
     */
    public function withQueryParams(array $query): ServerRequest
    {
        return (clone $this)->setQueryParams($query);
    }

    /**
     * Return the normalised uploaded files tree.
     *
     * @return array<string,UploadedFileInterface|array>
     */
    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    /**
     * Replace the uploaded files (in-place), normalising whatever shape the
     * caller passed in via {@see ServerRequest::normalizeFiles()}.
     *
     * @param  array<string,mixed> $uploadedFiles
     * @return $this
     * @throws \InvalidArgumentException When a leaf value is neither an UploadedFileInterface nor a valid $_FILES entry.
     */
    public function setUploadedFiles(array $uploadedFiles): self
    {
        $this->uploadedFiles = $this->normalizeFiles($uploadedFiles);

        return $this;
    }

    /**
     * Return a clone of the request with the uploaded files replaced.
     *
     * @param  array<string,mixed> $uploadedFiles
     * @return static
     * @throws \InvalidArgumentException When a leaf value is neither an UploadedFileInterface nor a valid $_FILES entry.
     */
    public function withUploadedFiles(array $uploadedFiles): self
    {
        return (clone $this)->setUploadedFiles($uploadedFiles);
    }

    /**
     * Return the parsed body — null, an object, or an array — as supplied
     * by a previous call to {@see ServerRequest::withParsedBody()}.
     *
     * @return null|object|array<int|string,mixed>
     */
    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    /**
     * Replace the parsed body (in-place). PSR-7 restricts the value to
     * null, an array or an object.
     *
     * @param  null|object|array<int|string,mixed> $data
     * @return $this
     * @throws \InvalidArgumentException When $data is not null, an array or an object.
     */
    public function setParsedBody($data): self
    {
        if(!is_array($data) && !is_object($data) && $data !== null){
            throw new \InvalidArgumentException('First parameter to withParsedBody MUST be object, array or null');
        }
        $this->parsedBody = $data;

        return $this;
    }

    /**
     * Return a clone of the request with the parsed body replaced.
     *
     * @param  null|object|array<int|string,mixed> $data
     * @return static
     * @throws \InvalidArgumentException When $data is not null, an array or an object.
     */
    public function withParsedBody($data): self
    {
        return (clone $this)->setParsedBody($data);
    }

    /**
     * Return all request attributes as an associative array.
     *
     * @return array<string,mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Return a single attribute by name, or $default when absent.
     *
     * @param  string $name
     * @param  mixed  $default
     * @return mixed
     */
    public function getAttribute($name, $default = null)
    {
        return $this->attributes[$name] ?? $default;
    }

    /**
     * Set a single attribute (in-place).
     *
     * @param  string $name
     * @param  mixed  $value
     * @return $this
     */
    public function setAttribute($name, $value): self
    {
        $this->attributes[$name] = $value;

        return $this;
    }

    /**
     * Return a clone of the request with the attribute set.
     *
     * @param  string $name
     * @param  mixed  $value
     * @return static
     */
    public function withAttribute($name, $value): self
    {
        return (clone $this)->setAttribute($name, $value);
    }

    /**
     * Remove an attribute (in-place). No-op when the attribute is absent.
     *
     * @param  string $name
     * @return $this
     */
    public function outAttribute($name): self
    {
        if (!isset($this->attributes[$name])) {
            return $this;
        }
        unset($this->attributes[$name]);

        return $this;
    }

    /**
     * Return a clone of the request with the attribute removed.
     *
     * @param  string $name
     * @return static
     */
    public function withoutAttribute($name): self
    {
        return (clone $this)->outAttribute($name);
    }

    /**
     * Convert PHP's $_FILES array (or a tree of pre-built
     * UploadedFileInterface instances) into a normalised structure of
     * UploadedFileInterface values. Arbitrarily nested input names of the
     * form `file[parent][child][...]` are supported by recursing through
     * the parallel arrays PHP produces.
     *
     * @param  array<string,mixed> $files
     * @return array<string,UploadedFileInterface|array>
     * @throws \InvalidArgumentException When a leaf value is neither an UploadedFileInterface nor a valid $_FILES entry.
     */
    public function normalizeFiles(array $files): array
    {
        $normalized = [];
        foreach ($files as $key => $value) {
            if ($value instanceof UploadedFileInterface) {
                $normalized[$key] = $value;
                continue;
            }
            if (!is_array($value)) {
                throw new \InvalidArgumentException('Invalid value in files specification');
            }
            if (isset($value['tmp_name'])) {
                $normalized[$key] = self::createUploadedFileFromSpec($value);
                continue;
            }
            $normalized[$key] = $this->normalizeFiles($value);
        }
        return $normalized;
    }

    /**
     * Build either a single UploadedFile or a tree of them from a single
     * $_FILES entry. Detects nested input names by inspecting whether
     * tmp_name is itself an array (PHP's signal for nested uploads).
     *
     * @param  array<string,mixed> $value
     * @return UploadedFileInterface|array<int|string,UploadedFileInterface|array>
     */
    private static function createUploadedFileFromSpec(array $value)
    {
        if (is_array($value['tmp_name'])) {
            return self::normalizeNestedFileSpec($value);
        }

        return new UploadedFile(
            $value['tmp_name'],
            isset($value['size']) ? (int) $value['size'] : null,
            (int) ($value['error'] ?? UPLOAD_ERR_OK),
            $value['name'] ?? null,
            $value['type'] ?? null
        );
    }

    /**
     * Walk the parallel arrays PHP populates for nested file uploads
     * (`file[a][b]`) and produce a matching UploadedFile tree, recursing
     * any additional levels via {@see self::createUploadedFileFromSpec()}.
     *
     * @param  array<string,mixed> $files
     * @return array<int|string,UploadedFileInterface|array>
     */
    private static function normalizeNestedFileSpec(array $files): array
    {
        $normalized = [];
        foreach (array_keys($files['tmp_name']) as $key) {
            $spec = [
                'tmp_name' => $files['tmp_name'][$key] ?? '',
                'size'     => $files['size'][$key]     ?? null,
                'error'    => $files['error'][$key]    ?? UPLOAD_ERR_OK,
                'name'     => $files['name'][$key]     ?? null,
                'type'     => $files['type'][$key]     ?? null,
            ];
            $normalized[$key] = self::createUploadedFileFromSpec($spec);
        }
        return $normalized;
    }

}
