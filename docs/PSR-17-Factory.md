# PSR-17 Factory

[PSR-17](https://www.php-fig.org/psr/psr-17/) defines six factory interfaces — one per PSR-7 message type. `InitPHP\HTTP\Factory\Factory` implements *all of them* in a single class:

```php
use InitPHP\HTTP\Factory\Factory;
$factory = new Factory();
```

The interfaces it implements:

```
Psr\Http\Message\RequestFactoryInterface
Psr\Http\Message\ResponseFactoryInterface
Psr\Http\Message\ServerRequestFactoryInterface
Psr\Http\Message\StreamFactoryInterface
Psr\Http\Message\UriFactoryInterface
Psr\Http\Message\UploadedFileFactoryInterface
```

One concrete factory + every interface means a single DI binding can satisfy any consumer that asks for "the factory" — whether they need one of the six PSR-17 sub-interfaces specifically or the union.

```php
// in any PSR-11 container
$container->set(
    \Psr\Http\Message\RequestFactoryInterface::class,
    fn () => new \InitPHP\HTTP\Factory\Factory()
);
```

## Method reference

### `createRequest(string $method, $uri): RequestInterface`

```php
$request = $factory->createRequest('POST', 'https://api.example.com/users');
```

Returns a [Request](Request.md) with empty headers, an empty body and HTTP/1.1. `$uri` can be a string or a `Psr\Http\Message\UriInterface`.

### `createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface`

```php
$response = $factory->createResponse(201);            // "Created"
$response = $factory->createResponse(418, 'Cuppa');   // explicit reason
```

Returns a [Response](Response.md). When `$reasonPhrase` is empty and the code is in the IANA table, the canonical phrase is used.

### `createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface`

```php
$request = $factory->createServerRequest('POST', '/users', $_SERVER);
```

Returns a [ServerRequest](Server-Request.md). Cookies, query, parsed body and uploaded files default to empty — set them via the `with*Params()` family.

### `createStream(string $content = ''): StreamInterface`

```php
$stream = $factory->createStream('payload');
```

Returns a [Stream](Stream.md) backed by `php://temp`, seeded with `$content`. This is the default backend for "in-memory but might spill to disk" payloads — the right choice for most cases.

### `createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface`

```php
$stream = $factory->createStreamFromFile('/var/files/report.pdf', 'rb');
```

Opens `$filename` with `$mode` and wraps the resulting resource. Failure modes:

- Empty `$filename` → `RuntimeException` ("Path cannot be empty").
- `$mode` empty or starting with an unrecognised letter → `InvalidArgumentException`. Valid first letters: `r`, `w`, `a`, `x`, `c`.
- `fopen()` returns false (file missing, permission denied) → `RuntimeException` carrying the `error_get_last()` message.

### `createStreamFromResource($resource): StreamInterface`

```php
$stream = $factory->createStreamFromResource(fopen('/dev/urandom', 'rb'));
```

Wraps an already-open resource. If `$resource` is already a `StreamInterface`, it's returned verbatim (no double-wrapping).

### `createUploadedFile(StreamInterface $stream, ?int $size = null, int $error = UPLOAD_ERR_OK, ?string $clientFilename = null, ?string $clientMediaType = null): UploadedFileInterface`

```php
$file = $factory->createUploadedFile($stream, 1024, UPLOAD_ERR_OK, 'me.png', 'image/png');
```

If `$size` is `null`, `$stream->getSize()` is consulted — which itself may be `null` (pipes, sockets, indeterminate streams). That's per the PSR-7 contract; `UploadedFile::getSize()` is nullable. See [UploadedFile](Uploaded-File.md).

> **v2 → v3 note:** Earlier versions used a non-nullable `int $size` parameter, which produced a `TypeError` whenever you passed a stream whose size couldn't be determined. Fixed in v3.

### `createUri(string $uri = ''): UriInterface`

```php
$uri = $factory->createUri('https://api.example.com/v1/users');
```

Parses `$uri` via PHP's `parse_url()`. An unparseable string raises `InvalidArgumentException`.

## Static facade

There's a `final` static facade for projects that prefer terseness over a DI binding:

```php
use InitPHP\HTTP\Facade\Factory;

$response = Factory::createResponse(200);
$stream   = Factory::createStream('payload');
```

The facade lazily resolves a singleton on first call. See [Facades](Facades.md) for the trade-offs (testability vs. ergonomics).

## See also

- [Quick Start](Quick-Start.md) — the factory in context.
- [Request](Request.md), [Response](Response.md), [Stream](Stream.md), [Uri](Uri.md), [UploadedFile](Uploaded-File.md) — the products.
