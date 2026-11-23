# API Reference

Every public method exposed by the package, grouped by class. This page is a quick map — for the *why* and the trade-offs, follow the page link for each class.

## Conventions

- **Italic** signatures are mutators that modify the instance in place (`set*`).
- Withers (`with*`) always return a new instance; the original is untouched.
- All concrete classes implement the matching PSR-7 / PSR-17 / PSR-18 interface — type-hint against the PSR interface unless you specifically need the convenience methods.

---

## `Message\Request` — [page](Request.md)

Implements: `Psr\Http\Message\RequestInterface`.

| Method                                                                                            | Returns                  |
|---------------------------------------------------------------------------------------------------|--------------------------|
| `__construct(string $method, $uri, array $headers = [], $body = null, string $version = '1.1')` | —                        |
| `getMethod()`                                                                                     | `string`                 |
| `withMethod($method)`                                                                             | `static`                 |
| *`setMethod($method)`*                                                                            | `self`                   |
| `getUri()`                                                                                        | `UriInterface`           |
| `withUri(UriInterface, bool $preserveHost = false)`                                               | `static`                 |
| *`setUri(UriInterface, bool $preserveHost = false)`*                                              | `self`                   |
| `getRequestTarget()`                                                                              | `string`                 |
| `withRequestTarget($requestTarget)`                                                               | `static`                 |
| *`setRequestTarget($requestTarget)`*                                                              | `self`                   |
| `isGet() / isPost() / isPut() / isDelete() / isHead() / isPatch()`                                | `bool`                   |
| `isMethod(string ...$method)`                                                                     | `bool`                   |

Plus everything from `MessageTrait` — see [`Message\Traits\MessageTrait`](#messagetraitsmessagetrait).

## `Message\Response` — [page](Response.md)

Implements: `Psr\Http\Message\ResponseInterface`.

| Method                                                                                        | Returns          |
|-----------------------------------------------------------------------------------------------|------------------|
| `__construct(int $status = 200, array $headers = [], $body = null, string $version = '1.1', ?string $reason = null)` | — |
| `getStatusCode()`                                                                              | `int`            |
| `withStatus($code, $reasonPhrase = '')`                                                        | `static`         |
| *`setStatusCode(int $code, string $reasonPhrase = '')`*                                        | `self`           |
| `getReasonPhrase()`                                                                            | `string`         |
| *`setStream($body)`*                                                                           | `self`           |
| `json(array $data = [], int $status = 200, int $flags = 0)`                                    | `self` (clone)   |
| `redirect($uri, int $status = 302, int $second = 0)`                                           | `self` (clone)   |

Constant: `Response::PHRASES` — `int => string` of IANA reason phrases.

Plus `MessageTrait`.

## `Message\ServerRequest` — [page](Server-Request.md)

Implements: `Psr\Http\Message\ServerRequestInterface`.

| Method                                                                            | Returns                                   |
|-----------------------------------------------------------------------------------|-------------------------------------------|
| `__construct(string $method, $uri, array $headers = [], $body = null, string $version = '1.1', array $serverParams = [])` | — |
| `static createFromGlobals(?array $server = null, ?array $get = null, ?array $post = null, ?array $cookies = null, ?array $files = null)` | `self` |
| `getServerParams() / getCookieParams() / getQueryParams() / getUploadedFiles() / getAttributes()` | `array`        |
| `getParsedBody()`                                                                  | `array|object|null`                       |
| `getAttribute($name, $default = null)`                                             | `mixed`                                   |
| `withCookieParams(array)`                                                          | `static`                                  |
| `withQueryParams(array)`                                                           | `static`                                  |
| `withUploadedFiles(array)`                                                         | `static`                                  |
| `withParsedBody($data)`                                                            | `static`                                  |
| `withAttribute($name, $value)`                                                     | `static`                                  |
| `withoutAttribute($name)`                                                          | `static`                                  |
| *`setCookieParams(array) / setQueryParams(array) / setUploadedFiles(array) / setParsedBody($data) / setAttribute($n, $v) / outAttribute($n)`* | `self` |
| `normalizeFiles(array $files)`                                                     | `array<string,UploadedFileInterface|array>` |

Plus `MessageTrait` + `RequestTrait`.

## `Message\Stream` — [page](Stream.md)

Implements: `Psr\Http\Message\StreamInterface`.

| Method                                       | Returns          |
|----------------------------------------------|------------------|
| `__construct($body = '', ?string $target = null)` | —          |
| `init($body = null, ?string $target = 'php://temp')` | `StreamInterface` |
| `__toString()`                               | `string` (never throws) |
| `__destruct()`                               | `void`           |
| `close()`                                    | `void`           |
| `detach()`                                   | `resource|null`  |
| `getSize()`                                  | `?int`           |
| `tell()`                                     | `int`            |
| `eof()`                                      | `bool`           |
| `isSeekable() / isReadable() / isWritable()` | `bool`           |
| `seek($offset, $whence = SEEK_SET)`          | `void`           |
| `rewind()`                                   | `void`           |
| `read($length)`                              | `string`         |
| `write($string)`                             | `int`            |
| `getContents()`                              | `string`         |
| `getMetadata($key = null)`                   | `array|mixed|null` |
| `isEmpty()`                                  | `bool`           |
| `isNotEmpty()`                               | `bool`           |

## `Message\Uri` — [page](Uri.md)

Implements: `Psr\Http\Message\UriInterface`.

| Method                                       | Returns          |
|----------------------------------------------|------------------|
| `__construct(string $uri = '')`              | —                |
| `__toString()`                               | `string`         |
| `getScheme() / getUserInfo() / getHost() / getPath() / getQuery() / getFragment()` | `string` |
| `getPort()`                                  | `?int`           |
| `getAuthority()`                             | `string`         |
| `withScheme($scheme) / withHost($host) / withPath($path) / withQuery($query) / withFragment($fragment)` | `static` |
| `withUserInfo($user, $password = null)`      | `static`         |
| `withPort($port)`                            | `static`         |
| *`setScheme(string) / setHost(string) / setPath(string) / setQuery(string) / setFragment(string) / setUserInfo(string, ?string) / setPort(?int)`* | `self` |

## `Message\UploadedFile` — [page](Uploaded-File.md)

Implements: `Psr\Http\Message\UploadedFileInterface`.

| Method                                                                             | Returns           |
|------------------------------------------------------------------------------------|-------------------|
| `__construct($streamOrFile, ?int $size, int $errorStatus, ?string $clientFilename = null, ?string $clientMediaType = null)` | — |
| `__destruct()`                                                                     | `void`            |
| `getStream()`                                                                      | `StreamInterface` |
| `moveTo($targetPath)`                                                              | `void`            |
| `getSize()`                                                                        | `?int`            |
| `getError()`                                                                       | `int`             |
| `getClientFilename()`                                                              | `?string`         |
| `getClientMediaType()`                                                             | `?string`         |

## `Message\Traits\MessageTrait`

Reused by `Request`, `Response`, `ServerRequest`. Adds:

| Method                                       | Returns          |
|----------------------------------------------|------------------|
| `getProtocolVersion()`                       | `string`         |
| `withProtocolVersion($version)`              | `static`         |
| *`setProtocolVersion(string)`*               | `self`           |
| `getHeaders()`                               | `array<string, string[]>` |
| `hasHeader($name)`                           | `bool`           |
| `getHeader($name)`                           | `string[]`       |
| `getHeaderLine($name)`                       | `string`         |
| `withHeader($name, $value)`                  | `static`         |
| `withAddedHeader($name, $value)`             | `static`         |
| `withoutHeader($name)`                       | `static`         |
| *`setHeader($n, $v) / setHeaders(array) / addedHeader($n, $v) / outHeader($n)`* | `self` |
| `getBody()`                                  | `StreamInterface` |
| `withBody(StreamInterface)`                  | `static`         |
| *`setBody(StreamInterface)`*                 | `self`           |
| `isEmpty() / isNotEmpty()`                   | `bool`           |

## `Factory\Factory` — [page](PSR-17-Factory.md)

Implements: every `Psr\Http\Message\*FactoryInterface`.

| Method                                                                                     | Returns                    |
|--------------------------------------------------------------------------------------------|----------------------------|
| `createRequest(string $method, $uri)`                                                       | `RequestInterface`         |
| `createResponse(int $code = 200, string $reasonPhrase = '')`                                | `ResponseInterface`        |
| `createServerRequest(string $method, $uri, array $serverParams = [])`                       | `ServerRequestInterface`   |
| `createStream(string $content = '')`                                                        | `StreamInterface`          |
| `createStreamFromFile(string $filename, string $mode = 'r')`                                | `StreamInterface`          |
| `createStreamFromResource($resource)`                                                       | `StreamInterface`          |
| `createUploadedFile(StreamInterface $stream, ?int $size = null, int $error = UPLOAD_ERR_OK, ?string $clientFilename = null, ?string $clientMediaType = null)` | `UploadedFileInterface` |
| `createUri(string $uri = '')`                                                               | `UriInterface`             |

## `Client\Client` — [page](PSR-18-Client.md) · [config](Client-Configuration.md) · [exceptions](Client-Exceptions.md)

Implements: `Psr\Http\Client\ClientInterface`.

| Method                                                                            | Returns                              |
|-----------------------------------------------------------------------------------|--------------------------------------|
| `__construct()`                                                                    | — (throws if `ext-curl` is missing)  |
| `sendRequest(RequestInterface $request)`                                           | `ResponseInterface`                  |
| `fetch(string $url, array $details = [])`                                          | `ResponseInterface`                  |
| `get(string $url, $body = null, array $headers = [], string $version = '1.1')`     | `ResponseInterface`                  |
| `post(...) / put(...) / patch(...) / delete(...) / head(...)`                      | `ResponseInterface`                  |
| `getUserAgent()`                                                                   | `string`                             |
| `setUserAgent(?string) / withUserAgent(?string)`                                   | `self`                               |
| `setTimeout(int) / withTimeout(int)`                                               | `self`                               |
| `setConnectTimeout(int) / withConnectTimeout(int)`                                 | `self`                               |
| `setFollowRedirects(bool, int $max = 10) / withFollowRedirects(bool, int $max = 10)` | `self`                            |
| `setCurlOptions(array) / withCurlOptions(array)`                                   | `self`                               |
| `getCurlOptions()`                                                                 | `array`                              |
| `setRetryPolicy(?RetryPolicy) / withRetryPolicy(?RetryPolicy)`                     | `self`                               |
| `getRetryPolicy()`                                                                 | `?RetryPolicy`                       |
| `setSleeper(SleeperInterface) / withSleeper(SleeperInterface)`                     | `self`                               |
| `setJitterRandomizer(?callable) / withJitterRandomizer(?callable)`                 | `self`                               |

The retry classes (`RetryPolicy`, `Backoff`, `RetryAfter`, `SleeperInterface`, `Sleeper`) are documented, with their signatures, on [Client Retry](Client-Retry.md).

## `Emitter\Emitter` — [page](Emitter.md)

| Method                                                                  | Returns |
|-------------------------------------------------------------------------|---------|
| `__construct(bool $strictMode = true)`                                  | —       |
| `emit(ResponseInterface $response, ?int $bufferLength = null)`          | `void`  |

## Facades — [page](Facades.md)

`final class InitPHP\HTTP\Facade\{Client, Emitter, Factory}`. Each forwards every static call to the singleton `getInstance()` returns. See the per-class `@method` PHPDoc on each facade for the IDE-friendly list of forwarded methods.

| Facade            | Singleton                                | Page                 |
|-------------------|------------------------------------------|----------------------|
| `Facade\Client`   | `Client\Client`                          | [Facades](Facades.md), [Client](PSR-18-Client.md) |
| `Facade\Emitter`  | `Emitter\Emitter` (strictMode = true)    | [Facades](Facades.md), [Emitter](Emitter.md) |
| `Facade\Factory`  | `Factory\Factory`                        | [Facades](Facades.md), [PSR-17 Factory](PSR-17-Factory.md) |

## Global helper — [page](Helpers.md)

```
send_request(
    string|RequestInterface $method,
    ?string $url = null,
    ?array  $headers = [],
    mixed   $body = null,
    ?string $version = null
): ResponseInterface
```

Auto-loaded via Composer's `files` autoload entry. Coerces array / object / `toArray()` bodies into JSON; forwards everything else to `Facade\Client`. See [Helpers](Helpers.md).

## Exceptions

| Class                                                  | Implements                                                  |
|--------------------------------------------------------|-------------------------------------------------------------|
| `Client\Exceptions\ClientException`                    | `Psr\Http\Client\ClientExceptionInterface`                  |
| `Client\Exceptions\NetworkException`                   | `Psr\Http\Client\NetworkExceptionInterface` (carries `RequestInterface`) |
| `Client\Exceptions\RequestException`                   | `Psr\Http\Client\RequestExceptionInterface` (carries `RequestInterface`) |
| `Emitter\Exceptions\EmitHeaderException`               | `\RuntimeException`                                          |
| `Emitter\Exceptions\EmitBodyException`                 | `\RuntimeException`                                          |

See [Client Exceptions](Client-Exceptions.md) for the catch surface and the cURL error-code map.
