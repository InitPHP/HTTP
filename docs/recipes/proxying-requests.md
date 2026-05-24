# Recipe: Proxying Requests

A common shape: an incoming `ServerRequest` should be forwarded to an upstream service, the response copied back. With the PSR-7 + PSR-18 building blocks the whole thing fits in 20 lines.

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

// 2) Strip hop-by-hop headers the proxy must NOT forward.
$hopByHop = ['Connection', 'Keep-Alive', 'Proxy-Authenticate', 'Proxy-Authorization',
             'TE', 'Trailers', 'Transfer-Encoding', 'Upgrade'];
$headers = array_diff_key($incoming->getHeaders(), array_flip($hopByHop));

// 3) Build the upstream request from the incoming body verbatim.
$outgoing = new Request(
    $incoming->getMethod(),
    $upstreamUri,
    $headers,
    $incoming->getBody(),
    $incoming->getProtocolVersion()
);

// 4) Ship it.
$client = (new Client())->withTimeout(30);
$response = $client->sendRequest($outgoing);

// 5) Emit the upstream response back to the caller, stripping hop-by-hop on the way out.
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

### Tracing

```php
$traceId = $incoming->getHeaderLine('X-Trace-Id') ?: bin2hex(random_bytes(8));
$outgoing = $outgoing->withHeader('X-Trace-Id', $traceId);
$response = $response->withHeader('X-Trace-Id', $traceId);
```

### Caching

The PSR-18 client gives you `ResponseInterface` directly — pipe it through any cache middleware you like before passing it to the emitter. The package itself ships no caching layer; that's deliberate.

### Streaming proxy

For large bodies, neither direction should be materialised as a string. The example above already streams: `withBody($incoming->getBody())` hands the upstream a `StreamInterface` reading from `php://input`, and the emitter's `bufferLength = 65536` streams the response back. The whole pipe is two-stage chunked I/O.

## What this does NOT replace

If you're standing up a *real* reverse proxy you almost certainly want nginx, HAProxy, or Caddy in front. The PSR-7/PSR-18 recipe above is for "I need to forward a single request inside business logic" cases — auth-decorating a downstream API, fan-out to multiple upstreams, etc.
