# InitPHP HTTP

Standards-compliant **PSR-7** message, **PSR-17** factory, **PSR-18** client and SAPI response **emitter** for PHP 7.4+.

[![Latest Stable Version](https://poser.pugx.org/initphp/http/v)](https://packagist.org/packages/initphp/http)
[![Total Downloads](https://poser.pugx.org/initphp/http/downloads)](https://packagist.org/packages/initphp/http)
[![License](https://poser.pugx.org/initphp/http/license)](https://packagist.org/packages/initphp/http)
[![PHP Version Require](https://poser.pugx.org/initphp/http/require/php)](https://packagist.org/packages/initphp/http)
[![Tests](https://github.com/InitPHP/HTTP/actions/workflows/tests.yml/badge.svg)](https://github.com/InitPHP/HTTP/actions/workflows/tests.yml)
[![Static Analysis](https://github.com/InitPHP/HTTP/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/InitPHP/HTTP/actions/workflows/static-analysis.yml)

A single dependency-light package that ships the four building blocks every PHP project ends up wiring by hand: immutable HTTP messages, a unified factory, a cURL-backed client, and an emitter that converts a `ResponseInterface` into bytes on the wire.

```bash
composer require initphp/http
```

```php
use InitPHP\HTTP\Facade\Factory;
use InitPHP\HTTP\Facade\Emitter;

$response = Factory::createResponse(200, 'OK')
    ->withHeader('Content-Type', 'text/plain; charset=utf-8');
$response->getBody()->write('Hello, world!');

Emitter::emit($response);
```

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
  - [Building a PSR-7 Request](#building-a-psr-7-request)
  - [Building a PSR-7 Response](#building-a-psr-7-response)
  - [Sending an HTTP request (PSR-18)](#sending-an-http-request-psr-18)
  - [Emitting a response (SAPI)](#emitting-a-response-sapi)
  - [Hydrating a ServerRequest from globals](#hydrating-a-serverrequest-from-globals)
  - [Using the static facades](#using-the-static-facades)
- [Documentation](#documentation)
- [PSR Compliance](#psr-compliance)
- [Migration from 2.x](#migration-from-2x)
- [Contributing](#contributing)
- [Security](#security)
- [License](#license)

---

## Features

- **PSR-7 v1 & v2 compatible** — works with the entire PSR-7 ecosystem.
- **PSR-17 factory** — one class implements every factory interface (`Request`, `Response`, `ServerRequest`, `Stream`, `UploadedFile`, `Uri`).
- **PSR-18 client** backed by **cURL** with sane production defaults: 30 s request timeout, 10 s connect timeout, redirect following, raw `CURLOPT_*` overrides without subclassing.
- **SAPI emitter** that streams the response to the browser with optional chunked output and `Content-Range` support.
- **Lazy static facades** for projects that prefer `Factory::createResponse()` over instantiating helpers explicitly.
- **Strict PSR-7 immutability** — `with*()` returns deep-cloned messages; mutating the clone never touches the original (verified by a dedicated immutability test suite).
- **Zero runtime dependencies** outside `psr/http-*`. `ext-curl` is only required when you actually use the client.
- Passes the upstream **`php-http/psr7-integration-tests`** and **`http-interop/http-factory-tests`** suites.

## Requirements

| Component       | Minimum            |
|-----------------|--------------------|
| PHP             | 7.4                |
| `ext-json`      | always required    |
| `ext-curl`      | required by `InitPHP\HTTP\Client\Client` (PSR-18 transport) |
| `psr/http-message`   | `^1.0 || ^2.0` |
| `psr/http-factory`   | `^1.0` |
| `psr/http-client`    | `^1.0` |

Tested on PHP 7.4, 8.0, 8.1, 8.2, 8.3 and 8.4 in CI.

## Installation

```bash
composer require initphp/http
```

## Quick Start

### Building a PSR-7 Request

```php
use InitPHP\HTTP\Message\Request;

$request = new Request(
    'POST',
    'https://api.example.com/users',
    ['Accept' => 'application/json'],
    json_encode(['name' => 'Ada']),
    '1.1'
);

$request = $request->withHeader('Content-Type', 'application/json; charset=utf-8');
```

### Building a PSR-7 Response

```php
use InitPHP\HTTP\Message\Response;

$response = (new Response())
    ->withStatus(201, 'Created')
    ->withHeader('Location', '/users/42');

$response->getBody()->write('{"id":42}');
```

Convenience producers on the concrete `Response`:

```php
$response = (new Response())->json(['id' => 42], 201);
$response = (new Response())->redirect('https://example.com/welcome', 302);
```

### Sending an HTTP request (PSR-18)

```php
use InitPHP\HTTP\Client\Client;
use InitPHP\HTTP\Message\Request;

$client = (new Client())
    ->withTimeout(10)
    ->withConnectTimeout(3)
    ->withUserAgent('my-app/1.0');

$response = $client->sendRequest(
    new Request('GET', 'https://httpbin.org/get')
);

echo $response->getStatusCode();          // 200
echo (string) $response->getBody();       // {"args":{},"headers":{...},...}
```

Higher-level helpers when you don't want to build a `Request` by hand:

```php
$response = $client->get('https://api.example.com/users');
$response = $client->post('https://api.example.com/users', '{"name":"Ada"}', [
    'Content-Type' => 'application/json',
]);
```

PSR-18 contract is honoured: **4xx/5xx responses are returned, not thrown**. Only transport failures raise `Psr\Http\Client\NetworkExceptionInterface`.

### Emitting a response (SAPI)

```php
use InitPHP\HTTP\Emitter\Emitter;

$emitter = new Emitter(/* strictMode: */ true);
$emitter->emit($response);                  // echoes body in one go

// For large bodies, stream in chunks:
$emitter->emit($response, 8192);
```

Range requests (`Content-Range: bytes 0-1023/...`) are honoured automatically.

### Hydrating a ServerRequest from globals

```php
use InitPHP\HTTP\Message\ServerRequest;

$request = ServerRequest::createFromGlobals();
// or with explicit data (recommended for tests and long-running runtimes):
$request = ServerRequest::createFromGlobals($server, $get, $post, $cookies, $files);
```

The factory is **stateless** — every call returns a fresh instance computed from the supplied arrays. Safe under Swoole, RoadRunner, Octane and FrankenPHP.

### Using the static facades

For projects that prefer terseness over explicit DI:

```php
use InitPHP\HTTP\Facade\Factory;
use InitPHP\HTTP\Facade\Client;
use InitPHP\HTTP\Facade\Emitter;

$request  = Factory::createRequest('GET', 'https://example.com');
$response = Client::sendRequest($request);
Emitter::emit($response);
```

Each facade lazily resolves a singleton on first call; subsequent calls return the same instance. Facades are entirely optional — every facade is just a thin static wrapper over a concrete service class you can instantiate yourself.

## Documentation

In-depth guides live under [`docs/`](docs/):

- [Getting Started](docs/getting-started.md)
- PSR-7 — [Messages](docs/psr7/messages.md), [ServerRequest](docs/psr7/server-request.md), [Streams](docs/psr7/streams.md), [Uri](docs/psr7/uri.md), [Uploaded Files](docs/psr7/uploaded-files.md)
- PSR-17 — [Factory](docs/psr17/factory.md)
- PSR-18 — [Client](docs/psr18/client.md), [Exceptions](docs/psr18/exceptions.md), [Configuration](docs/psr18/configuration.md)
- Emitter — [Basics](docs/emitter/basic-emission.md), [Chunked bodies](docs/emitter/chunked-bodies.md), [Content-Range](docs/emitter/content-range.md)
- Facades — [Overview](docs/facades/overview.md), [Customisation](docs/facades/customization.md)
- Recipes — [JSON responses](docs/recipes/json-response.md), [Redirects](docs/recipes/redirect.md), [File uploads](docs/recipes/file-upload.md), [Streaming large files](docs/recipes/streaming-large-files.md), [Proxying requests](docs/recipes/proxying-requests.md)
- [HTTP status code reference](docs/reference/http-status-codes.md)
- [Upgrade guide (2.x → 3.x)](docs/upgrade-guide.md)

## PSR Compliance

This package is verified against the official compliance suites:

| Suite                                                                                                  | Result   |
|--------------------------------------------------------------------------------------------------------|----------|
| [`php-http/psr7-integration-tests`](https://github.com/php-http/psr7-integration-tests)                | passing  |
| [`http-interop/http-factory-tests`](https://github.com/http-interop/http-factory-tests) (PSR-17)        | passing  |
| In-house PSR-18 smoke suite against the PHP built-in test server                                       | passing  |
| In-house PSR-7 immutability suite                                                                       | passing  |

Relevant specs: [PSR-7](https://www.php-fig.org/psr/psr-7/), [PSR-17](https://www.php-fig.org/psr/psr-17/), [PSR-18](https://www.php-fig.org/psr/psr-18/).

## Migration from 2.x

Version 3.0 is a breaking release. The highlights:

- The custom `InitPHP\HTTP\Message\Interfaces\*` interfaces have been **removed**. Type-hint against the PSR-7 interfaces directly (`Psr\Http\Message\RequestInterface`, etc.).
- `Request::createFromGlobals()` and the magic `$request->name = $value` parameter bag have been **removed**. Use `ServerRequest::createFromGlobals()` (now stateless) and `$request->getParsedBody()` instead.
- `Client::sendRequest()` no longer inspects the concrete `Request` class — it only sees the PSR-7 contract. Pre-encode array/DOM/XML payloads before calling.
- `Client` now defaults to a 30 s request timeout and 10 s connect timeout. Pass `->withTimeout(0)` if you need the legacy "no timeout" behaviour.
- The misspelled facade trait/interface names `Facadeble[Interface]` are now `Facadable[Interface]`; the old names remain as `@deprecated` aliases and will be removed in 4.0.

See [`docs/upgrade-guide.md`](docs/upgrade-guide.md) for the full migration walk-through and a copy-pasteable code-mod list.

## Contributing

Contributions are welcome — bug reports, doc improvements, performance patches, anything.

- Open issues and pull requests against [InitPHP/HTTP](https://github.com/InitPHP/HTTP).
- The project's organisation-wide [Contributing Guide](https://github.com/InitPHP/.github/blob/main/CONTRIBUTING.md) and [Code of Conduct](https://github.com/InitPHP/.github/blob/main/CODE_OF_CONDUCT.md) apply.
- Local development:

  ```bash
  composer install
  composer ci        # phpstan + phpunit
  ```

## Security

If you discover a security vulnerability please review the project's [Security Policy](https://github.com/InitPHP/.github/blob/main/SECURITY.md) and report it privately. **Please do not file public GitHub issues for security problems.**

## License

[MIT License](./LICENSE) — Copyright © Muhammet ŞAFAK and contributors.
