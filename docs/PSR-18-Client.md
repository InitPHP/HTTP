# PSR-18 Client

`InitPHP\HTTP\Client\Client` is the PSR-18 transport. It takes a `Psr\Http\Message\RequestInterface` and ships it over the wire via cURL, returning a `Psr\Http\Message\ResponseInterface`.

```php
use InitPHP\HTTP\Client\Client;
$client = new Client();
```

The constructor verifies `ext-curl` is loaded; if it isn't, you get a `ClientException` (PSR-18's `ClientExceptionInterface`).

## The PSR-18 entry point

```php
public function sendRequest(RequestInterface $request): ResponseInterface;
```

```php
use InitPHP\HTTP\Message\Request;

$response = $client->sendRequest(new Request('GET', 'https://httpbin.org/uuid'));

echo $response->getStatusCode();        // 200
echo (string) $response->getBody();     // {"uuid":"..."}
```

**4xx and 5xx are not exceptions.** They come back as normal responses. Only transport-level failures raise exceptions — see [Client Exceptions](Client-Exceptions.md).

## High-level verb helpers

For the common case where you don't want to build a full `Request`:

```php
$client->get(string $url, $body = null, array $headers = [], string $version = '1.1');
$client->post(string $url, $body = null, array $headers = [], string $version = '1.1');
$client->put(string $url, $body = null, array $headers = [], string $version = '1.1');
$client->patch(string $url, $body = null, array $headers = [], string $version = '1.1');
$client->delete(string $url, $body = null, array $headers = [], string $version = '1.1');
$client->head(string $url, $body = null, array $headers = [], string $version = '1.1');
```

Each is a one-line shortcut around `prepareRequest()` + `sendRequest()`:

```php
$response = $client->post(
    'https://api.example.com/users',
    json_encode(['name' => 'Ada']),
    ['Content-Type' => 'application/json'],
    '1.1'
);
```

## Generic `fetch()`

```php
$response = $client->fetch('https://api.example.com/users', [
    'method'  => 'POST',
    'body'    => $body,                  // string|resource|StreamInterface|null
    'headers' => ['Content-Type' => 'application/json'],
    'version' => '1.1',
]);
```

Keys are case-insensitive. Both `body` and `data` aliases are accepted for the payload.

## Body coercion

The client accepts only these body shapes:

| Type                | Behaviour                                            |
|---------------------|------------------------------------------------------|
| `null`              | Empty body                                           |
| `string`            | Sent verbatim                                        |
| `resource`          | Read into a `Stream` and sent                        |
| `StreamInterface`   | Used directly                                        |
| **anything else**   | `InvalidArgumentException` from `prepareRequest()`    |

This is deliberate: a PSR-18 client should not silently serialise arbitrary values — the codec choice (JSON, XML, form-encoded, ...) lives in the application, not the transport. If you want a convenience layer that auto-encodes arrays and `toArray()`-able objects as JSON, use the `send_request()` global helper — see [Helpers](Helpers.md).

> **v2 → v3 note:** The previous client coerced `array`, `DOMDocument`, `SimpleXMLElement`, and "any object" into a body. That cascade is gone. Pre-encode structured payloads before passing them in. See [Migration Guide](Migration-Guide.md).

## Response body backing

The returned `Response` carries a `Stream` backed by `php://temp` — small bodies stay in memory, larger bodies (>2 MiB by default) spill to disk transparently. Don't assume the body fits in a PHP string; for piping responses to other sinks, use `getBody()->read($bufferLen)` in a loop. See [Recipe — Streaming Large Files](Recipe-Streaming-Large-Files.md).

## Redirects

Redirects are followed by default (cURL's `CURLOPT_FOLLOWLOCATION = true`, up to 10 hops). The returned response represents the **final** leg — status, headers, and body all come from the final URL. Intermediate responses are not exposed.

Toggle / tune:

```php
$client = $client->withFollowRedirects(false);                          // disabled
$client = $client->withFollowRedirects(true, $maxRedirects = 3);        // up to 3 hops
```

## HTTP version

The client maps the request's `getProtocolVersion()` to the matching `CURL_HTTP_VERSION_*` constant:

| Request version  | cURL constant                |
|------------------|------------------------------|
| `'1.0'`          | `CURL_HTTP_VERSION_1_0`      |
| `'1.1'` (default) | `CURL_HTTP_VERSION_1_1`     |
| `'2'` / `'2.0'`  | `CURL_HTTP_VERSION_2_0`      |

HTTP/3 selection depends on your cURL build; add `[CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_3]` via `withCurlOptions()` if your binary supports it. See [Configuration](Client-Configuration.md).

## Empty-body POSTs

POST/PUT/PATCH/DELETE requests always set `CURLOPT_POSTFIELDS`, even when the body is empty. Without that, cURL silently downgrades the request to a body-less one — `CUSTOMREQUEST` alone is not enough. v3 handles this; v2 had a subtle bug where empty POSTs sometimes arrived at the server as GETs. See [Migration Guide](Migration-Guide.md).

## See also

- [Configuration](Client-Configuration.md) — timeouts, user agent, raw `CURLOPT_*` overrides.
- [Client Exceptions](Client-Exceptions.md) — the PSR-18 error contract.
- [Helpers](Helpers.md) — the `send_request()` global helper that auto-encodes arrays/objects.
- [Recipe — Proxying Requests](Recipe-Proxying-Requests.md) — incoming `ServerRequest` → outgoing `Request`.
