# PSR-7 Overview & Immutability

[PSR-7](https://www.php-fig.org/psr/psr-7/) defines six interfaces that model HTTP messages as immutable value objects. This package implements every one of them in `InitPHP\HTTP\Message\*`:

| PSR-7 contract                                | Concrete class                            | Page |
|-----------------------------------------------|-------------------------------------------|------|
| `Psr\Http\Message\RequestInterface`           | `InitPHP\HTTP\Message\Request`            | [Request](Request.md)               |
| `Psr\Http\Message\ResponseInterface`          | `InitPHP\HTTP\Message\Response`           | [Response](Response.md)             |
| `Psr\Http\Message\ServerRequestInterface`     | `InitPHP\HTTP\Message\ServerRequest`      | [ServerRequest](Server-Request.md)  |
| `Psr\Http\Message\StreamInterface`            | `InitPHP\HTTP\Message\Stream`             | [Stream](Stream.md)                 |
| `Psr\Http\Message\UriInterface`               | `InitPHP\HTTP\Message\Uri`                | [Uri](Uri.md)                       |
| `Psr\Http\Message\UploadedFileInterface`      | `InitPHP\HTTP\Message\UploadedFile`       | [UploadedFile](Uploaded-File.md)    |

All shared accessor/mutator code lives in two traits — `Message\Traits\MessageTrait` (headers, protocol, body) and `Message\Traits\RequestTrait` (method, URI, request target). Concrete classes consume those traits and add only the type-specific bits.

## The immutability contract

**Every method whose name starts with `with` returns a *new* instance.** The original is never modified. This is what allows PSR-15 middleware pipelines to pass messages between handlers without defensive copies:

```php
use InitPHP\HTTP\Message\Request;

$request = new Request('GET', '/');
$updated = $request->withHeader('X-Trace-Id', 'abc');

$request->hasHeader('X-Trace-Id'); // false — unchanged
$updated->hasHeader('X-Trace-Id'); // true
```

### Deep-cloning matters

PHP's default clone is *shallow*: a cloned `Request` would still point at the same `Stream` and `Uri` objects as the original. Writing to the clone's body would corrupt the original — exactly the bug PSR-7 immutability is designed to prevent.

This package implements `__clone()` on `Request`, `Response`, `ServerRequest`, and `Stream` to deep-clone the body and URI:

```php
$request = new Request('GET', '/', [], new Stream('original', 'php://temp'));
$clone   = $request->withHeader('X-Test', 'yes');

$clone->getBody()->write(' tampered');

(string) $request->getBody();  // "original"      — untouched
(string) $clone->getBody();    // " tamperedl"    — clone has its own buffer
```

For resource-backed `Stream`s the contents are read and copied into a fresh `php://temp` handle on clone; for the in-memory string backend PHP's copy-on-write does the work. Either way, the two messages are independent.

This behaviour is verified by the dedicated `tests/Immutability/MessageImmutabilityTest` suite — every supported message type, every `with*()` mutator.

### `with*()` vs `set*()`

The concrete classes also expose `set*()` mutators (`setHeader`, `setMethod`, `setStatusCode`, ...) that **modify the instance in place** and return `$this`. They exist because they make builders pleasant to write:

```php
$response = (new Response())
    ->setHeader('Content-Type', 'application/json')
    ->setBody($body)
    ->setStatusCode(201);
```

But the moment a message has been handed off to anything else, **treat it as frozen** and use `with*()`. The `set*()` methods are *implementation details* of the concrete class, not part of the PSR-7 interface — if you type-hint against `Psr\Http\Message\ResponseInterface` you only see `withStatus`, `withHeader`, etc.

```php
public function handle(\Psr\Http\Message\ResponseInterface $response): \Psr\Http\Message\ResponseInterface {
    // ✅ Works — PSR-7 surface
    return $response->withHeader('X-Trace-Id', '...');

    // ❌ Doesn't compile — setHeader is not on the interface
    // $response->setHeader('X-Trace-Id', '...');
}
```

## Type-hinting

In application code, **always type-hint against the PSR-7 interfaces**, not the concrete classes:

```php
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

public function controller(ServerRequestInterface $request): ResponseInterface {
    // ...
}
```

This is what makes the rest of the PHP ecosystem (PSR-15 middleware, frameworks, mocks) able to interoperate with your handlers. The concrete `InitPHP\HTTP\Message\*` types are simply one valid producer of those interfaces.

> **v2 → v3 note:** Previous versions of this package shipped a parallel set of interfaces under `InitPHP\HTTP\Message\Interfaces\*` that *extended* the PSR-7 contracts with mandatory `set*()` mutators. Those have been removed because the mandatory-mutator contract conflicted with PSR-7's prose. Always import from `Psr\Http\Message\*` now. See [Migration Guide](Migration-Guide.md).

## The headers oddity

PSR-7 header names are case-insensitive on lookup (`getHeader('content-type')` and `getHeader('Content-Type')` return the same value) but **case-preserving on storage** (the case you used when you set them is the case `getHeaders()` returns). This package stores both maps internally — a lowercase-keyed `headerNames` lookup and a case-preserved `headers` dictionary — so the two operations stay O(1).

```php
$response = (new Response())
    ->withHeader('X-Trace-Id', 'abc');

$response->getHeader('x-trace-id');      // ['abc']      — case-insensitive lookup
$response->getHeaderLine('X-TRACE-ID');  // 'abc'        — same
array_keys($response->getHeaders());      // ['X-Trace-Id'] — original case preserved
```

For multi-value headers (`Set-Cookie`, `Forwarded`, …) the value is always returned as an array; `getHeaderLine()` joins on `', '`.

```php
$response = $response->withAddedHeader('Set-Cookie', 'a=1')
                     ->withAddedHeader('Set-Cookie', 'b=2');

$response->getHeader('Set-Cookie');     // ['a=1', 'b=2']
$response->getHeaderLine('Set-Cookie'); // 'a=1, b=2'
```

## Where to next

- [Request](Request.md), [Response](Response.md), [ServerRequest](Server-Request.md) — concrete types.
- [Stream](Stream.md) — backend selection, fwrite semantics.
- [Uri](Uri.md) — parsing, mutators, percent-encoding rules.
- [UploadedFile](Uploaded-File.md) — `moveTo`, error states.
