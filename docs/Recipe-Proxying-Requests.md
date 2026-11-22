# Recipe — Proxying Requests

A common shape: an incoming `ServerRequest` should be forwarded to an upstream service, the response copied back. With the PSR-7 + PSR-18 building blocks the whole thing fits in twenty lines.

```php
use InitPHP\HTTP\Client\Client;
use InitPHP\HTTP\Message\Request;
use InitPHP\HTTP\Message\ServerRequest;
use InitPHP\HTTP\Emitter\Emitter;

$incoming = ServerRequest::createFromGlobals();

// 1) Rewrite the URI to point at the upstream host.
$upstreamUri = $incoming->getUri()
    ->withScheme('https')
    ->withHost('upstream.internal')
    ->withPort(443);

// 2) Strip hop-by-hop headers that MUST NOT be forwarded.
$hopByHop = ['Connection', 'Keep-Alive', 'Proxy-Authenticate', 'Proxy-Authorization',
             'TE', 'Trailers', 'Transfer-Encoding', 'Upgrade'];
$headers  = array_diff_key($incoming->getHeaders(), array_flip($hopByHop));

// 3) Build the upstream request from the incoming body verbatim.
$outgoing = new Request(
    $incoming->getMethod(),
    $upstreamUri,
    $headers,
    $incoming->getBody(),
    $incoming->getProtocolVersion()
);

// 4) Ship it.
$client   = (new Client())->withTimeout(30);
$response = $client->sendRequest($outgoing);

// 5) Strip hop-by-hop on the way out, then emit.
foreach ($hopByHop as $h) {
    $response = $response->withoutHeader($h);
}

(new Emitter())->emit($response, 65536);
```

## Useful extensions

### Forwarding the client's identity

```php
$outgoing = $outgoing
    ->withHeader('X-Forwarded-For',   $_SERVER['REMOTE_ADDR'] ?? '')
    ->withHeader('X-Forwarded-Proto', $incoming->getUri()->getScheme())
    ->withHeader('X-Forwarded-Host',  $incoming->getUri()->getHost());
```

### Trace IDs

```php
$traceId  = $incoming->getHeaderLine('X-Trace-Id') ?: bin2hex(random_bytes(8));
$outgoing = $outgoing->withHeader('X-Trace-Id', $traceId);
$response = $response->withHeader('X-Trace-Id', $traceId);
```

### Authentication

Add upstream auth on the way out (the incoming request's own `Authorization` header was already in `$headers`; strip it if you're using different credentials per leg):

```php
$outgoing = $outgoing
    ->withoutHeader('Authorization')
    ->withHeader('Authorization', 'Bearer ' . $upstreamToken);
```

### Caching

The PSR-18 client gives you the `ResponseInterface` directly — wrap it in any cache decorator before emitting:

```php
$response = $cache->fetchOrStore(
    cacheKey($outgoing),
    fn () => $client->sendRequest($outgoing)
);
```

The package itself ships no caching layer; that's deliberate.

### Streaming proxy

Neither direction should be materialised as a string for large bodies. The example above already streams: `withBody($incoming->getBody())` passes the upstream a `StreamInterface` reading from `php://input`, and `Emitter::emit($response, 65536)` streams the response back in chunks. The whole pipe is two-stage chunked I/O.

## What this does NOT replace

If you're standing up a *real* reverse proxy, you almost certainly want nginx, HAProxy, or Caddy in front. The PSR-7/PSR-18 recipe above is for "I need to forward a single request inside business logic" cases — auth-decorating a downstream API, fan-out to multiple upstreams, BFF (Backend-for-Frontend) layers, etc.

## See also

- [ServerRequest](Server-Request.md) — the receiving side.
- [PSR-18 Client](PSR-18-Client.md) — the sending side.
- [Emitter](Emitter.md) — the emitting side.
