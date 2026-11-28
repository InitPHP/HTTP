# InitPHP HTTP — Documentation

Welcome to the official documentation for **`initphp/http`** — a standards-compliant **PSR-7** (HTTP messages), **PSR-17** (HTTP factories) and **PSR-18** (HTTP client) implementation for PHP 8.2+, plus a SAPI response **emitter** and a small static-facade layer.

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

The package ships four building blocks every PHP service ends up wiring by hand:

| Layer  | Class / Facade                                                              | Purpose                                          |
|--------|-----------------------------------------------------------------------------|--------------------------------------------------|
| PSR-7  | `Message\{Request, Response, ServerRequest, Stream, Uri, UploadedFile}`     | Immutable HTTP message value objects             |
| PSR-17 | `Factory\Factory` / `Facade\Factory`                                        | One factory that satisfies every PSR-17 contract |
| PSR-18 | `Client\Client` / `Facade\Client`                                           | cURL-backed transport with sane production defaults |
| SAPI   | `Emitter\Emitter` / `Facade\Emitter`                                        | Push a `Response` to the web server              |

## Start here

- **New to the package?** Read [Installation](Installation.md), then [Quick Start](Quick-Start.md).
- **Building a Request or Response?** See [Request](Request.md) and [Response](Response.md).
- **Receiving an incoming HTTP request?** See [ServerRequest](Server-Request.md).
- **Sending HTTP requests?** See [PSR-18 Client](PSR-18-Client.md) and [Client Configuration](Client-Configuration.md).
- **Returning JSON / redirects / file downloads?** Browse the [Recipes](Recipes.md).
- **Need every public method?** The [API Reference](API-Reference.md) is exhaustive.
- **Upgrading from v2?** Read the [Migration Guide](Migration-Guide.md).

## Why pick this one

- **PSR-7 v1 *and* v2** compatible (`psr/http-message: ^1.0 || ^2.0`).
- **Strict PSR-7 immutability** — `with*()` returns a deep-cloned message; writing to the clone never touches the original. Verified by a dedicated immutability test suite.
- **Production cURL defaults** — 30 s request timeout, 10 s connect timeout, transparent redirect following, full `CURLOPT_*` override surface.
- **Stateless `ServerRequest::createFromGlobals()`** — safe under Swoole, RoadRunner, Octane and FrankenPHP (no static caching).
- **Content-Type-aware body parsing** — JSON, urlencoded forms and multipart all land in `getParsedBody()` automatically.
- **Lazy static facades** — `Factory::createResponse()` instead of `(new Factory())->createResponse()` when you want it.
- **Zero runtime dependencies** outside `psr/http-*`. `ext-curl` is only required when you actually use the client.
- Passes the upstream **`php-http/psr7-integration-tests`** and **`http-interop/http-factory-tests`** suites.

## At a glance — feature matrix

| Capability                                                                | Status |
|---------------------------------------------------------------------------|:------:|
| `Psr\Http\Message\RequestInterface`                                       | ✅ |
| `Psr\Http\Message\ResponseInterface`                                      | ✅ |
| `Psr\Http\Message\ServerRequestInterface`                                 | ✅ |
| `Psr\Http\Message\StreamInterface`                                        | ✅ |
| `Psr\Http\Message\UriInterface`                                           | ✅ |
| `Psr\Http\Message\UploadedFileInterface`                                  | ✅ |
| `Psr\Http\Message\MessageInterface`                                       | ✅ |
| `Psr\Http\Factory\*FactoryInterface` (all six)                            | ✅ |
| `Psr\Http\Client\ClientInterface` (PSR-18)                                | ✅ |
| `Psr\Http\Client\{Client, Network, Request}ExceptionInterface`            | ✅ |
| Deep-clone immutability for `with*()`                                     | ✅ |
| Content-Type-aware `ServerRequest::createFromGlobals()`                   | ✅ |
| nginx + php-fpm header collection (no `apache_request_headers()` needed)  | ✅ |
| Nested `$_FILES` (`file[parent][child][...]`) normalisation               | ✅ |
| Stream backends: `php://temp`, `php://memory`, in-memory string, resource | ✅ |
| Emitter with `Content-Range` support                                      | ✅ |

## Package metadata

- **License**: MIT
- **Minimum PHP**: 8.2 (tested on 8.2, 8.3, 8.4, 8.5, 8.6)
- **Runtime dependencies**: `psr/http-message ^1.0 || ^2.0`, `psr/http-factory ^1.0`, `psr/http-client ^1.0`, `ext-json`
- **Optional**: `ext-curl` (only required by `Client\Client`)
- **Packagist**: [`initphp/http`](https://packagist.org/packages/initphp/http)
- **Source**: [github.com/InitPHP/HTTP](https://github.com/InitPHP/HTTP)
- **Issues**: [github.com/InitPHP/HTTP/issues](https://github.com/InitPHP/HTTP/issues)
- **Discussions**: [github.com/orgs/InitPHP/discussions](https://github.com/orgs/InitPHP/discussions)
- **Security**: [`SECURITY.md`](https://github.com/InitPHP/.github/blob/main/SECURITY.md)

If something in this documentation is unclear, ambiguous, or wrong, please [open an issue](https://github.com/InitPHP/HTTP/issues/new) — documentation fixes are reviewed eagerly.
