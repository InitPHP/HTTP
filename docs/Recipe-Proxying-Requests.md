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

// 2) Hop-by-hop headers MUST NOT be forwarded. Header names are
//    case-insensitive ("transfer-encoding" is the same header), and any header
//    named in "Connection: a, b" is hop-by-hop as well.
function hopByHopNames(\Psr\Http\Message\MessageInterface $message): array
{
    $names = ['connection', 'keep-alive', 'proxy-authenticate', 'proxy-authorization',
              'te', 'trailer', 'transfer-encoding', 'upgrade'];
    foreach (explode(',', $message->getHeaderLine('Connection')) as $name) {
        $names[] = strtolower(trim($name));
    }

    return $names;
}

// 3) Build the upstream request from the incoming body verbatim. "Host" is not
//    copied either: the Request derives it from $upstreamUri, so the upstream
//    sees its own host and not the one the client chose.
$skip     = [...hopByHopNames($incoming), 'host'];
$outgoing = new Request(
    $incoming->getMethod(),
    $upstreamUri,
    [],
    $incoming->getBody(),
    $incoming->getProtocolVersion()
);
foreach ($incoming->getHeaders() as $name => $values) {
    if (!in_array(strtolower((string) $name), $skip, true)) {
        $outgoing = $outgoing->withHeader((string) $name, $values);
    }
}

// 4) Ship it.
$client   = (new Client())->withTimeout(30);
$response = $client->sendRequest($outgoing);

// 5) Strip hop-by-hop on the way out, then emit.
foreach (hopByHopNames($response) as $h) {
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

The PSR-18 client gives you the `ResponseInterface` directly — wrap it in any cache decorator before emitting. Only cache what is identical for every caller: a `GET` that carries no credentials. A response fetched with an `Authorization` or `Cookie` header belongs to one user, and a cache key that ignores those headers would hand it to the next:

```php
$cacheable = $outgoing->getMethod() === 'GET'
    && !$outgoing->hasHeader('Authorization')
    && !$outgoing->hasHeader('Cookie');

$response = $cacheable
    ? $cache->fetchOrStore((string) $outgoing->getUri(), fn () => $client->sendRequest($outgoing))
    : $client->sendRequest($outgoing);
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
