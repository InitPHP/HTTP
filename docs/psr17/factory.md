# PSR-17 Factory

`InitPHP\HTTP\Factory\Factory` implements every PSR-17 sub-factory interface in one class:

- `RequestFactoryInterface::createRequest($method, $uri)`
- `ResponseFactoryInterface::createResponse($code, $reasonPhrase)`
- `ServerRequestFactoryInterface::createServerRequest($method, $uri, $serverParams)`
- `StreamFactoryInterface::createStream($content)`
- `StreamFactoryInterface::createStreamFromFile($filename, $mode)`
- `StreamFactoryInterface::createStreamFromResource($resource)`
- `UriFactoryInterface::createUri($uri)`
- `UploadedFileFactoryInterface::createUploadedFile($stream, $size, $error, $clientFilename, $clientMediaType)`

One concrete factory + every interface means a single instance can be type-hinted against any of them — useful when you DI-inject "the factory" into many consumers that each only care about one sub-interface.

## Construction

```php
use InitPHP\HTTP\Factory\Factory;

$factory = new Factory();
```

No constructor arguments, no configuration. Wire it as a singleton in your container:

```php
// any PSR-11 container
$container->set(
    \Psr\Http\Message\RequestFactoryInterface::class,
    fn () => new \InitPHP\HTTP\Factory\Factory()
);
```

## Reference

### `createRequest(string $method, $uri): RequestInterface`

Returns a `Message\Request` with empty headers, an empty body and HTTP/1.1.

### `createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface`

Returns a `Message\Response`. If `$reasonPhrase` is empty and an IANA phrase is known for `$code`, the IANA phrase is used.

### `createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface`

Returns a `Message\ServerRequest` with the supplied server params. Cookies / query / parsedBody / uploadedFiles default to empty; set them with the `with*Params()` family.

### `createStream(string $content = ''): StreamInterface`

Returns a `Stream` backed by `php://temp` and seeded with `$content`. Use this for "in-memory but might spill to disk" payloads — the most common default.

### `createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface`

Opens `$filename` with `$mode` and wraps the resulting resource. `$mode` is validated against the standard `fopen()` modes; an empty path raises `RuntimeException`, an unreadable file raises `RuntimeException` carrying the underlying `error_get_last()` message, and an unrecognised mode raises `InvalidArgumentException`.

### `createStreamFromResource($resource): StreamInterface`

Wraps an already-open resource. If the argument is already a `StreamInterface`, it's returned verbatim (no double-wrapping).

### `createUploadedFile(StreamInterface $stream, ?int $size = null, int $error = UPLOAD_ERR_OK, ?string $clientFilename = null, ?string $clientMediaType = null): UploadedFileInterface`

If `$size` is `null`, `$stream->getSize()` is used (which itself may be `null` for pipes/sockets — that's fine, `UploadedFile::getSize()` is nullable per PSR-7).

### `createUri(string $uri = ''): UriInterface`

Parses `$uri` via PHP's `parse_url()`. An unparseable string raises `InvalidArgumentException`.

## Static facade

There's also a static facade if you'd rather not instantiate:

```php
use InitPHP\HTTP\Facade\Factory;

$response = Factory::createResponse(200);
```

See [Facades / Overview](../facades/overview.md) for the trade-offs.
