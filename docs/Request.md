# Request

`InitPHP\HTTP\Message\Request` is the PSR-7 outbound-request message: the kind you build to *send*, as opposed to a `ServerRequest` (the kind you receive). It implements `Psr\Http\Message\RequestInterface`.

```php
use InitPHP\HTTP\Message\Request;
```

## Construction

```php
$request = new Request(
    string $method,             // "GET", "POST", ...
    string|UriInterface $uri,   // URL string or UriInterface
    array $headers = [],        // header name => value or list of values
    string|resource|StreamInterface|null $body = null,
    string $version = '1.1'     // HTTP protocol version
);
```

Examples:

```php
// Bare GET
$request = new Request('GET', 'https://api.example.com/users');

// JSON POST
$request = new Request(
    'POST',
    'https://api.example.com/users',
    ['Content-Type' => 'application/json; charset=utf-8'],
    json_encode(['name' => 'Ada'])
);

// Using a pre-built Uri value object
$uri = new \InitPHP\HTTP\Message\Uri('https://api.example.com/v1/users?active=true');
$request = new Request('GET', $uri);
```

## Reading

```php
$request->getMethod();              // "POST"
$request->getUri();                 // UriInterface
$request->getRequestTarget();       // "/users?active=true"
$request->getProtocolVersion();     // "1.1"
$request->getHeaders();             // array<string, string[]>
$request->getHeader('Accept');      // string[] — empty array if missing
$request->getHeaderLine('Accept');  // "application/json, text/plain"
$request->hasHeader('Accept');      // bool — case-insensitive
$request->getBody();                // StreamInterface
```

`getRequestTarget()` returns the explicit override (set via `withRequestTarget()`) or, failing that, the URI's path + query (`/users?active=true`). When the path is empty it falls back to `/`.

## Convenience method predicates

On top of the PSR-7 surface, `Request` adds case-insensitive method-check helpers:

```php
$request->isGet();
$request->isPost();
$request->isPut();
$request->isPatch();
$request->isDelete();
$request->isHead();

// Multi-method check
$request->isMethod('PUT', 'PATCH');  // true if method matches any
```

These come from `RequestTrait` and are shared with [ServerRequest](Server-Request.md).

## Mutating (returns a new instance)

```php
$updated = $request
    ->withMethod('PUT')
    ->withUri(new Uri('https://api.example.com/users/42'))
    ->withRequestTarget('*')                // OPTIONS *
    ->withHeader('Content-Type', 'application/json; charset=utf-8')
    ->withAddedHeader('X-Trace-Id', '0f1e2d')
    ->withoutHeader('Accept')
    ->withBody($newStream)
    ->withProtocolVersion('2');
```

Each call returns a fresh `Request`; the original is untouched. See [Overview & Immutability](PSR-7-Overview.md) for the deep-clone rules that make this safe.

### Host header synchronisation

`withUri(...)` updates the `Host` header from the new URI unless you pass `preserveHost = true` **and** the request already has a Host header:

```php
$request = new Request('GET', 'https://original.example/path');
$request->getHeaderLine('Host');  // "original.example"

$updated = $request->withUri(new Uri('https://elsewhere.example/'));
$updated->getHeaderLine('Host');  // "elsewhere.example"

$pinned = $request->withHeader('Host', 'sticky.example')
    ->withUri(new Uri('https://elsewhere.example/'), true);
$pinned->getHeaderLine('Host');   // "sticky.example"  — preserveHost honoured
```

Non-standard ports are included in the synchronised Host header; standard ports (`80` for `http`, `443` for `https`) are omitted:

```php
$request->withUri(new Uri('http://b.example:8080/'))->getHeaderLine('Host'); // "b.example:8080"
$request->withUri(new Uri('https://b.example:443/'))->getHeaderLine('Host'); // "b.example"
```

## Setting the body from many shapes

The constructor (and `setBody()`) accept any of these:

| Shape              | What happens                                                  |
|--------------------|---------------------------------------------------------------|
| `null`             | An empty body is created lazily on the first `getBody()` call. |
| `string`           | Wrapped in a new `Stream` (default backend: `php://temp`).     |
| `resource`         | Wrapped in a new `Stream` directly.                            |
| `StreamInterface`  | Stored verbatim.                                               |

See [Stream](Stream.md) for the backend selection (`php://temp` vs `php://memory` vs in-memory string vs raw resource).

## Sending a Request

`Request` is just the value object — it does not know how to actually send itself. Pass it to a PSR-18 client:

```php
use InitPHP\HTTP\Client\Client;

$response = (new Client())->sendRequest($request);
```

See [PSR-18 Client](PSR-18-Client.md).

## Things that changed in v3

Earlier versions of this package bolted a `_parameters` parameter bag onto `Request` (`__get`, `__set`, `all`, `get`, `has`, `merge`) along with a `Request::sendRequest()` shortcut and a static `Request::createFromGlobals()` singleton. **All of that is gone in v3**:

- `Request` is now strictly a PSR-7 message value object.
- The parameter bag was a separate concern — use `ServerRequest::getParsedBody()` / attributes instead. See [ServerRequest](Server-Request.md).
- The static factory moved to `ServerRequest::createFromGlobals()` and is now stateless. See [ServerRequest](Server-Request.md).
- `sendRequest()` shortcut is gone — pass the request to a `Client` you constructed yourself (good for DI / testing).

If you're upgrading, the [Migration Guide](Migration-Guide.md) has a copy-pasteable code-mod for every removed API.
