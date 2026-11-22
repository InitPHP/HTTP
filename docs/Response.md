# Response

`InitPHP\HTTP\Message\Response` is the PSR-7 response message. It implements `Psr\Http\Message\ResponseInterface`.

```php
use InitPHP\HTTP\Message\Response;
```

## Construction

```php
$response = new Response(
    int $status = 200,
    array $headers = [],
    string|resource|StreamInterface|null $body = null,
    string $version = '1.1',
    ?string $reason = null     // null → IANA-canonical reason for $status
);
```

Examples:

```php
// Bare 200
$response = new Response();

// 404 with a plain body
$response = new Response(404, ['Content-Type' => 'text/plain'], 'Nothing here.');

// 201 with an explicit reason override
$response = new Response(201, [], '{"id":42}', '1.1', 'Created Already');
```

When `$reason` is `null` and the status is in the IANA table, the canonical phrase is used: `200 → "OK"`, `404 → "Not Found"`, `500 → "Internal Server Error"`, etc. The full table lives in `Response::PHRASES`; see [HTTP Status Codes](HTTP-Status-Codes.md).

### Supported HTTP versions

The constructor validates against this allow-list:

```
'1.0' | '1.1' | '2' | '2.0' | '3' | '3.0'
```

Both `'2'` (PSR-7 canonical) and `'2.0'` are accepted; same for HTTP/3. Anything else raises `InvalidArgumentException`.

## Reading

```php
$response->getStatusCode();     // int
$response->getReasonPhrase();   // string
$response->getProtocolVersion();// "1.1"
$response->getHeaders();        // array<string, string[]>
$response->getHeader('X-Trace');// string[]
$response->getHeaderLine('X-Trace'); // string
$response->hasHeader('Content-Type'); // bool
$response->getBody();           // StreamInterface
```

## Mutating (returns a new instance)

```php
$updated = $response
    ->withStatus(404)
    ->withStatus(418, "I'm a coffee maker, actually")
    ->withHeader('Content-Type', 'application/problem+json')
    ->withAddedHeader('Set-Cookie', 'a=1')
    ->withoutHeader('X-Internal')
    ->withBody($newStream);
```

`withStatus()` enforces the range `100..599` — anything outside raises `InvalidArgumentException`.

## Convenience producers

### `json()`

```php
$response = (new Response())->json(['ok' => true, 'data' => $rows], 200);

// Optional flags ORed into json_encode():
$response = (new Response())->json($data, 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
```

What this does:

- Sets `Content-Type: application/json; charset=utf-8`.
- Encodes with `JSON_THROW_ON_ERROR` always set (plus any flags you pass). Unencodable payloads raise `InvalidArgumentException` instead of silently producing the literal string `false`.
- Replaces the body with a fresh in-memory string-backed `Stream` containing the encoded JSON.
- Returns a *new* `Response` (clone) — original untouched.

### `redirect()`

```php
$response = (new Response())->redirect('https://example.com/welcome');                      // 302
$response = (new Response())->redirect('https://example.com/welcome', 301);                 // permanent
$response = (new Response())->redirect('https://example.com/welcome', 200, 5);              // delayed
```

What this does:

- Always sets `Location: <uri>`, so non-browser clients (HTTP libraries, crawlers, monitoring) can follow the redirect — regardless of the delay argument.
- When `$second > 0`, **additionally** sets `Refresh: <seconds>; url=<uri>` so browsers honour the countdown.
- Sets the status to the supplied code (default 302).
- Accepts both a string URL and a `Psr\Http\Message\UriInterface`. Anything else raises `InvalidArgumentException`.
- Returns a *new* `Response`.

> **v2 → v3 note:** Previously the delay branch *replaced* `Location` with `Refresh`. v3 always emits `Location` and adds `Refresh` on top when a delay is supplied. See [Migration Guide](Migration-Guide.md).

## In-place mutators (rare — use inside a builder only)

`Response` carries `set*()` siblings for the `with*()` methods (`setStatusCode`, `setStream`, `setHeader` from `MessageTrait`, etc.) which mutate in place. They exist to make construction pleasant but are not part of the `Psr\Http\Message\ResponseInterface` contract — type-hinted code never sees them. See [Overview & Immutability](PSR-7-Overview.md).

```php
// Inside the same expression that constructs the response — fine.
$response = (new Response())
    ->setStatusCode(201)
    ->setHeader('Location', '/users/42');

// After the response has been handed to anything else — use with*().
$audited = $response->withHeader('X-Trace-Id', $traceId);
```

## Sending a Response to the wire

`Response` is a value object — it does not know how to write bytes to the HTTP server. Use the [Emitter](Emitter.md):

```php
use InitPHP\HTTP\Emitter\Emitter;
(new Emitter())->emit($response);
```

## See also

- [Recipe — JSON Response](Recipe-JSON-Response.md) for the JSON convenience producer plus manual variants.
- [Recipe — Redirect](Recipe-Redirect.md) for 301/302/303/307 with and without delay.
- [Recipe — Streaming Large Files](Recipe-Streaming-Large-Files.md) for serving multi-MB bodies.
- [HTTP Status Codes](HTTP-Status-Codes.md) for the full reason-phrase table.
