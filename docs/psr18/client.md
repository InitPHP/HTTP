# PSR-18 Client

`InitPHP\HTTP\Client\Client` is the PSR-18 transport. It sends a `Psr\Http\Message\RequestInterface` over cURL and returns a `Psr\Http\Message\ResponseInterface`.

```php
use InitPHP\HTTP\Client\Client;
use InitPHP\HTTP\Message\Request;

$client   = new Client();
$response = $client->sendRequest(new Request('GET', 'https://httpbin.org/uuid'));
```

The constructor verifies `ext-curl` is loaded; if it isn't, it raises `Psr\Http\Client\ClientExceptionInterface`.

## High-level helpers

`sendRequest()` is the strict PSR-18 entry point. For the common case where you don't want to build a `Request` by hand, the client exposes verb-named helpers:

```php
$client->get(string $url, $body = null, array $headers = [], string $version = '1.1');
$client->post(string $url, $body = null, array $headers = [], string $version = '1.1');
$client->put(string $url, $body = null, array $headers = [], string $version = '1.1');
$client->patch(string $url, $body = null, array $headers = [], string $version = '1.1');
$client->delete(string $url, $body = null, array $headers = [], string $version = '1.1');
$client->head(string $url, $body = null, array $headers = [], string $version = '1.1');
```

And a generic `fetch()` for fully-described calls:

```php
$response = $client->fetch('https://api.example.com/users', [
    'method'  => 'POST',
    'body'    => $jsonBody,            // string|resource|StreamInterface|null
    'headers' => ['Content-Type' => 'application/json'],
    'version' => '1.1',
]);
```

The keys are case-insensitive; both `body` and `data` work.

## Body coercion

The client only accepts these body shapes:

| Type                | Behaviour                                |
|---------------------|------------------------------------------|
| `null`              | Empty body                               |
| `string`            | Sent verbatim                            |
| `resource`          | Read into a `Stream` and sent            |
| `StreamInterface`   | Used directly                            |
| **anything else**   | `InvalidArgumentException`               |

This is deliberate: a PSR-18 client should not silently serialise objects — that responsibility lives in the application layer where the codec choice (JSON, XML, form-encoded, MessagePack, ...) is actually decided. If you want a convenience layer that auto-encodes arrays and `toArray()`-able objects as JSON, use the `send_request()` helper.

## Response bodies

The returned `Response` carries a `Stream` backed by `php://temp` — small responses stay in memory, larger ones spill to disk transparently. Don't assume the body fits in a string; use `getBody()->read($bufferLen)` in a loop when you're piping the response to another sink.

## Redirects

Redirects are followed by default (cURL's `CURLOPT_FOLLOWLOCATION = true`, up to 10 hops). The returned response represents the **final** leg — status, headers, and body all come from the final URL. The intermediate responses are not exposed.

Disable or tune:

```php
$client = $client->withFollowRedirects(false);
// or
$client = $client->withFollowRedirects(true, $maxRedirects = 3);
```

## HTTP/2 and HTTP/3

The client maps the message's `getProtocolVersion()` to the corresponding `CURL_HTTP_VERSION_*` constant. Versions `'2'` and `'2.0'` both select HTTP/2; `'1.0'`, `'1.1'` and the default cover the other cases. HTTP/3 selection depends on your cURL build — add it through `withCurlOptions([CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_3])` if your linker supports it.

## See also

- [Configuration](configuration.md) — timeouts, user agent, custom cURL options.
- [Exceptions](exceptions.md) — PSR-18 error contract and the difference between `NetworkException` and `RequestException`.
