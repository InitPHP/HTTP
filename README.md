# InitPHP HTTP

This library provides a self-contained HTTP toolkit for PHP:

- **PSR-7** HTTP Message implementation (`Request`, `Response`, `ServerRequest`, `Stream`, `UploadedFile`, `Uri`)
- **PSR-17** HTTP Factory implementation
- **PSR-18** HTTP Client implementation (backed by `ext-curl`)
- An `Emitter` for PSR-7 responses
- Convenience `Facade` shortcuts

Starting with **2.2**, this package supersedes three previously separate packages — see [Migrating from deprecated packages](#migrating-from-deprecated-packages) below.

[![Latest Stable Version](http://poser.pugx.org/initphp/http/v)](https://packagist.org/packages/initphp/http) [![Total Downloads](http://poser.pugx.org/initphp/http/downloads)](https://packagist.org/packages/initphp/http) [![Latest Unstable Version](http://poser.pugx.org/initphp/http/v/unstable)](https://packagist.org/packages/initphp/http) [![License](http://poser.pugx.org/initphp/http/license)](https://packagist.org/packages/initphp/http) [![PHP Version Require](http://poser.pugx.org/initphp/http/require/php)](https://packagist.org/packages/initphp/http)

## Requirements

- PHP 7.4 or higher
- `ext-curl` (used by the PSR-18 client)
- `ext-json`
- PSR-7 HTTP Message Interfaces
- PSR-17 HTTP Factories Interfaces
- PSR-18 HTTP Client Interfaces

## Installation

```
composer require initphp/http
```

## Usage

It adheres to the PSR-7, PSR-17, PSR-18 standards and strictly implements these interfaces to a large extent.

### PSR-7 Emitter Usage

```php
use \InitPHP\HTTP\Message\{Response, Stream};
use \InitPHP\HTTP\Emitter\Emitter;


$response = new Response(200, [], new Stream('Hello World', null), '1.1');

$emitter = new Emitter();
$emitter->emit($response);
```

or 

```php
use \InitPHP\HTTP\Facade\Factory;
use \InitPHP\HTTP\Facade\Emitter;

$response = Factory::createResponse(200);
$response->getBody()->write('Hello World');

Emitter::emit($response);
```

### PSR-17 Factory Usage

```php
use \InitPHP\HTTP\Factory\Factory;

$httpFactory = new Factory();

/** @var \Psr\Http\Message\RequestInterface $request */
$request = $httpFactory->createRequest('GET', 'http://example.com');

// ...
```

or

```php
use InitPHP\HTTP\Facade\Factory;

/** @var \Psr\Http\Message\RequestInterface $request */
$request = Factory::createRequest('GET', 'http://example.com');
```

### PSR-18 Client Usage

```php
use \InitPHP\HTTP\Message\Request;
use \InitPHP\HTTP\Client\Client;

$request = new Request('GET', 'http://example.com');

$client = new Client();

/** @var \Psr\Http\Message\ResponseInterface $response */
$response = $client->sendRequest($request);
```

or

```php
use \InitPHP\HTTP\Facade\Factory;
use \InitPHP\HTTP\Facade\Client;

$request = Factory::createRequest('GET', 'http://example.com');

/** @var \Psr\Http\Message\ResponseInterface $response */
$response = Client::sendRequest($request);
```



#### A Small Difference For PSR-7 Stream

If you are working with small content; The PSR-7 Stream interface may be cumbersome for you. This is because the PSR-7 stream interface writes the content "`php://temp`" or "`php://memory`". By default this library will also overwrite `php://temp` with your content. To change this behavior, this must be declared as the second parameter to the constructor method when creating the Stream object.

```php
use \InitPHP\HTTP\Stream;

/**
 * This content is kept in memory as a variable.
 */
$variableStream = new Stream('String Content', null);

/**
 * Content; "php://memory" is overwritten.
 */
$memoryStream = new Stream('Content', 'php://memory');

/**
 * Content; "php://temp" is overwritten.
 */
$tempStream = new Stream('Content', 'php://temp');
// or new Stream('Content');
```

## Migrating from deprecated packages

Three previously separate InitPHP packages have been merged into this one as of **2.2.0** and are now deprecated. `initphp/http:^2.2` declares Composer `replace` entries for all three, so Composer will not install them side-by-side once you depend on the consolidated package.

### Migrating from `initphp/http-factory`

The class name changed (`HTTPFactory` → `Factory`) and the namespace moved, but the public API is unchanged. **A `class_alias` shim keeps the old fully-qualified class name working**, so existing code compiles without changes:

```php
// Old code (no change required, alias keeps this working)
use InitPHP\HTTPFactory\HTTPFactory;
$factory = new HTTPFactory();
```

Recommended new code:

```php
use InitPHP\HTTP\Factory\Factory;
$factory = new Factory();
```

### Migrating from `initphp/http-client`

The old `\InitPHP\HTTPClient\Client` was a thin PSR-18 wrapper around the standalone `initphp/curl` package and accepted an options array via its constructor. The canonical PSR-18 client in this package (`\InitPHP\HTTP\Client\Client`) talks to `ext-curl` directly and exposes setter methods. **No transparent alias is provided** because the constructor signatures are incompatible.

**Before:**

```php
use InitPHP\HTTPClient\Client;
use InitPHP\HTTP\Request; // legacy namespace

$client = new Client([
    'allow_redirect' => true,
    'max_redirect'   => 3,
    'timeout'        => 0,
]);

$response = $client->sendRequest(new Request('GET', 'https://example.com'));
```

**After:**

```php
use InitPHP\HTTP\Client\Client;
use InitPHP\HTTP\Message\Request;

$client = new Client();
$client->setUserAgent('My App/1.0');

$response = $client->sendRequest(new Request('GET', 'https://example.com'));
```

If you depended on the `allow_redirect`, `max_redirect`, `timeout`, `proxy`, or `ssl` options of the old client, those translate to direct `CURLOPT_*` settings the new client applies by default. If you need to customise them, please open an issue — exposing a richer options API on the canonical client is on the roadmap.

### Migrating from `initphp/curl`

The standalone `initphp/curl` package was a low-level wrapper around `ext-curl`. The canonical PSR-18 client now calls `curl_*` directly and is the recommended entry point for HTTP requests. **There is no drop-in replacement for the `\InitPHP\Curl\Curl` class** — if you used it to issue HTTP requests, move that code to `\InitPHP\HTTP\Client\Client`. If you used it for non-HTTP cURL operations, continue to call `curl_*` directly; a thin wrapper around the cURL extension does not warrant a dedicated package.

## Credits

- [Muhammet ŞAFAK](https://www.muhammetsafak.com.tr) <<info@muhammetsafak.com.tr>>

## License

Copyright &copy; 2022 [MIT License](./LICENSE)
