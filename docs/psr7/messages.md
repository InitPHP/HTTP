# PSR-7 Messages: Request and Response

PSR-7 messages are **immutable**. Every method whose name starts with `with` (e.g. `withHeader`, `withStatus`, `withUri`) returns a **new** instance — the original is left untouched. The concrete classes in this package also expose `set*()` mutators for ergonomics, but you should reach for those only inside a builder; once a message has left your construction site, treat it as frozen.

## Request

```php
use InitPHP\HTTP\Message\Request;

$request = new Request(
    'POST',                                  // method
    'https://api.example.com/users',         // URI as string or UriInterface
    ['Accept' => 'application/json'],        // headers (string or list of strings)
    json_encode(['name' => 'Ada']),          // body (string|resource|StreamInterface|null)
    '1.1'                                    // HTTP protocol version
);
```

The constructor accepts both a string URI and a `Psr\Http\Message\UriInterface`. Headers can be `string|string[]` per RFC 7230 (multi-value headers like `Set-Cookie` arrive as arrays).

### Reading

```php
$request->getMethod();           // "POST"
$request->getUri();              // UriInterface
$request->getRequestTarget();    // "/users"   (path + query)
$request->getHeaders();          // array<string, string[]>
$request->getHeader('Accept');   // string[]   — empty array if missing
$request->getHeaderLine('Accept'); // "application/json, text/plain"
$request->getProtocolVersion();  // "1.1"
$request->getBody();             // StreamInterface
```

`getHeader()` is case-insensitive. The keys returned from `getHeaders()` preserve the case the header was originally stored with (PSR-7 mandates only that the case is consistent on the wire).

### Convenience predicates

The concrete `Request` adds a handful of method-check helpers on top of PSR-7:

```php
$request->isGet();      // bool
$request->isPost();
$request->isPut();
$request->isPatch();
$request->isDelete();
$request->isHead();
$request->isMethod('PUT', 'PATCH');  // matches either
```

All are case-insensitive.

### Mutating (returns a new instance)

```php
$with = $request
    ->withMethod('PUT')
    ->withUri(new Uri('https://api.example.com/users/42'))
    ->withHeader('Content-Type', 'application/json; charset=utf-8')
    ->withAddedHeader('X-Trace-Id', '0f1e2d')
    ->withoutHeader('Accept')
    ->withProtocolVersion('2');
```

Every `with*()` call returns a fresh message; the original `$request` keeps its original shape. This is how all the PSR-15 middleware in the ecosystem assumes messages behave.

### Bodies

`withBody()` accepts any `Psr\Http\Message\StreamInterface`:

```php
use InitPHP\HTTP\Message\Stream;

$request = $request->withBody(new Stream(json_encode($payload), 'php://temp'));
```

See [Streams](streams.md) for the details on `target` selection (`php://temp`, `php://memory`, or `null` for an in-memory string backend).

## Response

```php
use InitPHP\HTTP\Message\Response;

$response = new Response(
    200,                          // status
    ['Content-Type' => 'text/plain'], // headers
    null,                         // body
    '1.1',                        // version
    null                          // reason phrase override (null => IANA default)
);
```

If `reason` is `null` and `status` is a recognised code (100..511), the canonical IANA phrase is used (`'OK'`, `'Internal Server Error'`, `'Not Found'`, ...).

```php
$response->getStatusCode();    // 200
$response->getReasonPhrase();  // "OK"
$response = $response->withStatus(404);
$response = $response->withStatus(418, "I'm a teapot");
```

Supported HTTP versions: `1.0`, `1.1`, `2`, `2.0`, `3`, `3.0`.

### Convenience producers

```php
$response = (new Response())->json(['ok' => true, 'data' => $rows], 200);
```

- Sets `Content-Type: application/json; charset=utf-8`.
- Uses `JSON_THROW_ON_ERROR`; an unencodable payload raises `InvalidArgumentException` instead of silently producing `false`.
- Optional third argument `$flags` is ORed into `json_encode()`.

```php
$response = (new Response())
    ->redirect('https://example.com/welcome', 302);

// With a "thanks for shopping" countdown:
$response = (new Response())
    ->redirect('https://example.com/welcome', 302, 5); // Refresh: 5; url=...
```

The Location header is **always** set so non-browser clients (crawlers, HTTP libraries, monitoring) can follow the redirect. When the countdown argument is non-zero, a `Refresh` header is added in addition to `Location`.

## Why deep cloning matters

The default PHP clone is shallow: cloning a `Request` would leave both copies pointing at the same `Stream` and `Uri` objects. Writing to the clone's body would corrupt the original — exactly the bug PSR-7 immutability is designed to prevent.

Concrete classes in this package implement `__clone()` to deep-clone the body and URI:

```php
$request = new Request('GET', '/');
$clone   = $request->withHeader('X-Test', 'yes');

$clone->getBody()->write('payload');

(string) $request->getBody();  // ""        — original untouched
(string) $clone->getBody();    // "payload" — clone has its own buffer
```

This guarantee is verified by `tests/Immutability/MessageImmutabilityTest.php`.
