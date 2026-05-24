# Getting Started

## Install

```bash
composer require initphp/http
```

Optional but recommended:

```bash
composer require --dev phpunit/phpunit "^9.6 || ^10.5"
composer require --dev phpstan/phpstan "^1.10"
```

## A complete round-trip in 12 lines

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use InitPHP\HTTP\Client\Client;
use InitPHP\HTTP\Message\Request;

$client   = new Client();
$response = $client->sendRequest(new Request('GET', 'https://httpbin.org/uuid'));

echo 'HTTP ' . $response->getStatusCode() . PHP_EOL;
echo (string) $response->getBody() . PHP_EOL;
```

Three things just happened:

1. `Request` constructed a PSR-7 request value object.
2. `Client::sendRequest()` shipped it over the wire via cURL.
3. The returned `Response` is a PSR-7 value object you can pass through middlewares, log, or transform.

## What this package is — and isn't

It **is** the four PSR building blocks a PHP service needs to speak HTTP without manually writing cURL:

| Layer  | Class / Facade                                | Purpose                                      |
|--------|-----------------------------------------------|----------------------------------------------|
| PSR-7  | `Message\{Request,Response,ServerRequest,Stream,Uri,UploadedFile}` | Immutable HTTP messages |
| PSR-17 | `Factory\Factory`                             | A single factory that satisfies every PSR-17 sub-interface |
| PSR-18 | `Client\Client`                               | cURL-backed PSR-18 transport                 |
| SAPI   | `Emitter\Emitter`                             | Push a `Response` to the running web server  |

It **isn't** a router, a middleware dispatcher, or a request-handler pipeline (PSR-15). Those concerns live in higher-level packages — this library focuses on the message layer.

## Running the test suite

```bash
git clone https://github.com/InitPHP/HTTP.git
cd HTTP
composer install
composer ci   # phpstan + phpunit
```

The suite includes the upstream `php-http/psr7-integration-tests` and `http-interop/http-factory-tests` compliance packs, plus an in-house PSR-18 smoke test against the PHP built-in server and a dedicated immutability suite.

## Next steps

- Read [PSR-7 Messages](psr7/messages.md) for the immutability rules and the `with*()` vs `set*()` distinction.
- Read [PSR-18 Configuration](psr18/configuration.md) before shipping the client to production — the defaults are sane but not exhaustive.
- Skim the [Recipes](README.md#recipes) for one-page solutions to the common shapes.
