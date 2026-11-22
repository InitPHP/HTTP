# Helpers

The package ships one global helper function, registered via Composer's `files` autoload entry:

## `send_request()`

```php
function send_request(
    string|\Psr\Http\Message\RequestInterface $method,
    ?string $url = null,
    ?array  $headers = [],
    mixed   $body = null,
    ?string $version = null
): \Psr\Http\Message\ResponseInterface;
```

A convenience wrapper around `InitPHP\HTTP\Facade\Client` with **body coercion** baked in — the kind of "I just want to send this object" shortcut you'd otherwise write yourself.

### Two argument shapes

#### 1. Pre-built PSR-7 request

```php
$response = send_request($request);                  // $request is a RequestInterface
```

Forwarded to `\InitPHP\HTTP\Facade\Client::sendRequest($request)` verbatim. No coercion.

#### 2. Method + URL + loose configuration

```php
$response = send_request(
    'POST',
    'https://api.example.com/users',
    ['Accept' => 'application/json'],
    ['name' => 'Ada'],                  // array → JSON-encoded + Content-Type added
    '1.1'
);
```

This branch goes through `\InitPHP\HTTP\Facade\Client::fetch()` with body coercion applied at the helper level (before the PSR-18 client's strict body rules kick in).

### Body coercion rules

| Input                                        | Behaviour                                                                                                |
|----------------------------------------------|----------------------------------------------------------------------------------------------------------|
| `null`                                       | Passed through as `null`.                                                                                |
| `string`                                     | Passed through verbatim.                                                                                 |
| `resource`                                   | Passed through; the client wraps it in a `Stream`.                                                       |
| `Psr\Http\Message\StreamInterface`           | Passed through; the client uses it directly.                                                              |
| `array`                                      | `json_encode(...)`; if no `Content-Type` was supplied, `application/json; charset=utf-8` is added.       |
| Object with `__toString()`                   | Coerced to string via `(string) $body`.                                                                   |
| Object with `toArray()`                      | `json_encode($body->toArray())`; same Content-Type defaulting as the array case.                          |
| Object with neither                          | `InvalidArgumentException` with a guidance message.                                                       |

When both `__toString()` and `toArray()` exist, **`__toString()` wins**.

### Failure modes

| Cause                                                                         | Exception                            |
|-------------------------------------------------------------------------------|--------------------------------------|
| First argument is a string but `$url` is `null`                                | `InvalidArgumentException`           |
| Body is an object with neither `__toString()` nor `toArray()` nor `StreamInterface` | `InvalidArgumentException`     |
| Transport failure (DNS, TCP, TLS, timeout)                                     | `Psr\Http\Client\NetworkExceptionInterface` |
| Underlying client setup failure (ext-curl missing, ...)                        | `Psr\Http\Client\ClientExceptionInterface`  |

### Examples

```php
// Object with toArray()
class Payload { public function toArray(): array { return ['k' => 'v']; } }
$response = send_request('POST', 'https://api.example.com/in', [], new Payload());
// Body becomes {"k":"v"}; Content-Type defaults to application/json; charset=utf-8

// Object with __toString()
$body = new class { public function __toString(): string { return 'raw'; } };
$response = send_request('POST', 'https://api.example.com/in',
    ['Content-Type' => 'text/plain'], $body);
// Body becomes 'raw'; Content-Type kept as text/plain.

// Pre-built request, no coercion
use InitPHP\HTTP\Message\Request;
$response = send_request(new Request('GET', 'https://api.example.com/users'));
```

### Why it lives separately from the Client

The PSR-18 `Client` is intentionally strict — it accepts only stream-shaped bodies. Mixing in array / object coercion at that level would force every consumer to opt out of the magic when they really do want the raw bytes. The convenience layer sits on top so that:

- Library code can depend on the strict PSR-18 surface.
- Application code can use the ergonomic helper.
- The two never fight over what `array` means.

## Disabling the helper

`send_request()` is guarded by `function_exists()` — if your application already defines a `send_request()` function (unlikely but possible), Composer's autoloader skips ours. If you want to *prevent* loading the helper entirely, you can fork the `composer.json` and drop the `files` autoload entry — but in practice the function-exists guard plus the unique name make conflicts essentially impossible.

## See also

- [PSR-18 Client](PSR-18-Client.md) — the underlying transport (strict body rules).
- [Facades](Facades.md) — the static layer `send_request()` ultimately forwards through.
