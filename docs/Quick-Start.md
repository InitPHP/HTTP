# Quick Start

A ten-minute tour of every layer the package ships, with copy-pasteable code.

## 1. Build a Request

```php
use InitPHP\HTTP\Message\Request;

$request = new Request(
    'POST',                                  // method
    'https://api.example.com/users',         // URI as string or UriInterface
    ['Accept' => 'application/json'],        // headers — string or list-of-strings
    json_encode(['name' => 'Ada']),          // body — string|resource|StreamInterface|null
    '1.1'                                    // HTTP protocol version
);
```

Every PSR-7 message is **immutable**:

```php
$updated = $request->withHeader('X-Trace-Id', '0f1e2d');
// $request still has no X-Trace-Id; $updated does
```

See [Request](Request.md) for the full surface.

## 2. Build a Response

```php
use InitPHP\HTTP\Message\Response;

$response = (new Response(200))
    ->withHeader('Content-Type', 'text/plain; charset=utf-8');

$response->getBody()->write('Hello!');
```

Two convenience producers ship on `Response` for the cases that come up most often:

```php
$response = (new Response())->json(['ok' => true], 200);
$response = (new Response())->redirect('https://example.com/welcome', 302);
```

See [Response](Response.md), [Recipe — JSON Response](Recipe-JSON-Response.md) and [Recipe — Redirect](Recipe-Redirect.md).

## 3. Send an HTTP request (PSR-18)

```php
use InitPHP\HTTP\Client\Client;
use InitPHP\HTTP\Message\Request;

$client = (new Client())
    ->withTimeout(10)
    ->withConnectTimeout(3)
    ->withUserAgent('my-app/1.0');

$response = $client->sendRequest(
    new Request('GET', 'https://httpbin.org/uuid')
);

echo $response->getStatusCode();        // 200
echo (string) $response->getBody();     // {"uuid":"..."}
```

There are higher-level helpers when you don't want to build a `Request` by hand:

```php
$client->get('https://api.example.com/users');
$client->post('https://api.example.com/users', '{"name":"Ada"}', [
    'Content-Type' => 'application/json',
]);
$client->fetch('https://api.example.com/users', [
    'method'  => 'POST',
    'body'    => json_encode(['name' => 'Ada']),
    'headers' => ['Content-Type' => 'application/json'],
]);
```

PSR-18 contract is honoured: **4xx and 5xx responses are returned, not thrown.** Only transport-level failures raise exceptions. See [Client Exceptions](Client-Exceptions.md).

## 4. Receive an incoming request (server-side)

```php
use InitPHP\HTTP\Message\ServerRequest;

$request = ServerRequest::createFromGlobals();

$request->getMethod();        // "GET", "POST", ...
$request->getUri()->getPath();
$request->getQueryParams();   // $_GET
$request->getParsedBody();    // automatically parsed for JSON / urlencoded forms
$request->getUploadedFiles(); // normalised UploadedFile tree
$request->getAttribute('user');
```

The factory is **stateless** — each call returns a fresh instance. See [ServerRequest](Server-Request.md).

## 5. Emit a Response to the browser

```php
use InitPHP\HTTP\Emitter\Emitter;

(new Emitter())->emit($response);

// For multi-MB bodies, stream in chunks:
(new Emitter())->emit($response, 65536);
```

The emitter writes the status line, headers and body in the order any reverse proxy expects. See [Emitter](Emitter.md), [Chunked Bodies](Chunked-Bodies.md) and [Content-Range](Content-Range.md).

## 6. Optional: skip the wiring with static facades

```php
use InitPHP\HTTP\Facade\Factory;
use InitPHP\HTTP\Facade\Client;
use InitPHP\HTTP\Facade\Emitter;

$request  = Factory::createRequest('GET', 'https://example.com');
$response = Client::sendRequest($request);
Emitter::emit($response);
```

Each facade lazily resolves a singleton on first call. See [Facades](Facades.md).

## End-to-end: handle an incoming JSON POST and reply

```php
use InitPHP\HTTP\Message\Response;
use InitPHP\HTTP\Message\ServerRequest;
use InitPHP\HTTP\Emitter\Emitter;

$request = ServerRequest::createFromGlobals();

if ($request->getMethod() !== 'POST') {
    $response = (new Response(405, ['Allow' => 'POST']))
        ->withHeader('Content-Type', 'text/plain');
    $response->getBody()->write('Method Not Allowed');
} else {
    $body = $request->getParsedBody() ?? [];
    $response = (new Response())->json([
        'received' => $body,
        'remote'   => $request->getServerParams()['REMOTE_ADDR'] ?? null,
    ], 201);
}

(new Emitter())->emit($response);
```

## Where to next

- **Type-hint against PSR-7?** Read [PSR-7 Overview](PSR-7-Overview.md).
- **Production-harden the Client?** Read [Client Configuration](Client-Configuration.md).
- **Receive file uploads?** Read [UploadedFile](Uploaded-File.md).
- **Coming from v2.x?** Read the [Migration Guide](Migration-Guide.md).
