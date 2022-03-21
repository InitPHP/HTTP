# InitPHP HTTP

This library provides HTTP Message and HTTP Factory solution following PSR-7 and PSR-17 standards. It also includes an Emitter class for the PSR-7.

[![Latest Stable Version](http://poser.pugx.org/initphp/http/v)](https://packagist.org/packages/initphp/http) [![Total Downloads](http://poser.pugx.org/initphp/http/downloads)](https://packagist.org/packages/initphp/http) [![Latest Unstable Version](http://poser.pugx.org/initphp/http/v/unstable)](https://packagist.org/packages/initphp/http) [![License](http://poser.pugx.org/initphp/http/license)](https://packagist.org/packages/initphp/http) [![PHP Version Require](http://poser.pugx.org/initphp/http/require/php)](https://packagist.org/packages/initphp/http)

## Requirements

- PHP 7.4 or higher
- PSR-7 HTTP Message Interfaces
- PSR-17 HTTP Factories Interfaces

## Installation

```
composer require initphp/http
```

## Usage

It adheres to the PSR-7 and PSR-17 standards and strictly implements these interfaces to a large extent.

### Emitter Usage

```php
use \InitPHP\HTTP\{Response, Emitter, Stream};


$response = new Response(200, [], new Stream('Hello World', null), '1.1');

$emitter = new Emitter;
$emitter->emit($response);
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

## Credits

- [Muhammet ŞAFAK](https://www.muhammetsafak.com.tr) <<info@muhammetsafak.com.tr>>

## License

Copyright &copy; 2022 [MIT License](./LICENSE)
