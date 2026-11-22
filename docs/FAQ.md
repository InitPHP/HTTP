# FAQ

## Do I need `ext-curl`?

Only if you use `InitPHP\HTTP\Client\Client` (the PSR-18 transport). PSR-7 messages, the PSR-17 factory and the SAPI emitter work without it. The `Client` constructor checks for `ext-curl` and throws a `ClientException` if it's missing.

## Is this PSR-7 v1 or v2?

Both. The composer constraint is `psr/http-message: ^1.0 || ^2.0`. The concrete classes satisfy both contracts.

## Why is `Stream::__toString()` returning `""` for my closed stream?

Because PSR-7 explicitly forbids `__toString()` from throwing. The empty-string behaviour is the spec-mandated failure mode. If you want a hard error on a broken stream, call `getContents()` directly — it still throws `RuntimeException`. See [Stream](Stream.md).

## Why does my 500 response now say "Internal Server Error" instead of "Internal ServerRequest Error"?

Because that was a typo in the v2 reason-phrase table that this release fixes. See [HTTP Status Codes](HTTP-Status-Codes.md) and the [Migration Guide](Migration-Guide.md).

## My PSR-18 client used to time out infinitely. Why isn't it doing that any more?

The default timeout changed from `0` (infinite) to `30` seconds in v3 because an unresponsive endpoint would tie up a PHP-FPM worker indefinitely otherwise. For genuine infinite-wait scenarios (long-poll, SSE) be explicit:

```php
$client = (new Client())->withTimeout(0)->withConnectTimeout(10);
```

See [Client Configuration](Client-Configuration.md).

## How do I send an `array` body now that the Client rejects it?

Two options:

1. **Pre-encode it** (preferred — explicit about the content type):
   ```php
   $client->post($url, json_encode($data), ['Content-Type' => 'application/json']);
   ```
2. **Use the `send_request()` helper** which still encodes arrays as JSON:
   ```php
   $response = send_request('POST', $url, [], $data);
   ```

See [Helpers](Helpers.md).

## Why was `Request::createFromGlobals()` moved to `ServerRequest`?

Two reasons:

- **It's the conceptually correct home.** `Request` is the outbound type; `ServerRequest` is the inbound type. Hydration from `$_SERVER`/`$_GET`/`$_POST` is an inbound concept.
- **The singleton was a real bug.** v2 cached the first result in a static property and reused it forever — under long-running PHP (Swoole, RoadRunner, Octane, FrankenPHP) the second request silently saw the first request's data. v3 is stateless.

See [ServerRequest](Server-Request.md).

## How do I access form input now that `$request->name` is gone?

Use the PSR-7 parsed-body API:

```php
$parsed = $request->getParsedBody() ?? [];
$name   = $parsed['name'] ?? null;
```

`ServerRequest::createFromGlobals()` populates `parsedBody` automatically when the request advertises `application/json`, `application/x-www-form-urlencoded`, or `multipart/form-data`. See [ServerRequest](Server-Request.md).

## Can I inject a mock `Client` for tests?

Yes. Direct DI works fine; the concrete `Client\Client` is just one valid producer of `Psr\Http\Client\ClientInterface`. Type-hint against the interface and pass a mock / fake / stub in your tests.

```php
final class WeatherService {
    public function __construct(private \Psr\Http\Client\ClientInterface $http) {}
    // ...
}

// In a test
$service = new WeatherService($mockClient);
```

If you used the static `Facade\Client`, switch the affected code to constructor DI — facades are best left for prototypes and quick scripts. See [Facades](Facades.md).

## Does the package handle multipart upload encoding?

No, deliberately. PSR-7 has no opinion on multipart encoding; this package keeps the HTTP layer unaware of it so you can plug in `guzzlehttp/psr7`'s `MultipartStream`, `symfony/mime`, or your own encoder. See [Recipe — File Upload](Recipe-File-Upload.md) for examples.

## Does the package implement PSR-15 (middleware)?

No. PSR-15 is a separate concern (request-handler pipelines) that lives in higher-level packages — `relay/relay`, `laminas/laminas-stratigility`, framework pipelines, etc. This package focuses on the message layer those pipelines pass around.

## How big is the response body the Client will buffer?

The response body is staged on `php://temp`, which keeps small payloads in memory and spills to disk past PHP's default 2 MiB threshold. So you don't pay RAM for the full response, regardless of size — but you do pay disk I/O for very large bodies. For multi-GB downloads, read the body incrementally instead of `(string) $body`-ing it. See [Recipe — Streaming Large Files](Recipe-Streaming-Large-Files.md).

## Are 4xx/5xx responses thrown as exceptions?

No. PSR-18 mandates that only transport failures throw; protocol-level errors (4xx / 5xx) come back as regular `ResponseInterface` instances. Check `getStatusCode()` and throw yourself if your application semantics need it. See [Client Exceptions](Client-Exceptions.md).

## What's the difference between `set*()` and `with*()`?

`set*()` mutates the instance in place and returns `$this`. `with*()` returns a clone, leaving the original untouched. Use `with*()` everywhere the message has been handed off — it's the PSR-7 contract. Use `set*()` only inside a builder where you fully own the in-flight message. See [Overview & Immutability](PSR-7-Overview.md).

## Why are the `set*()` methods not in the interfaces?

Because PSR-7 prose mandates immutability. Putting mutators in the interface contract would let any callable accept your message and silently mutate it — exactly what PSR-7 is designed to prevent. The convenience mutators on the concrete classes are *implementation details*, not contract.

> v2 → v3 note: earlier versions of this package shipped its own interfaces under `InitPHP\HTTP\Message\Interfaces\*` that *did* include the mutators. They're gone in v3 for this reason. See the [Migration Guide](Migration-Guide.md).

## Does the documentation cover everything?

Almost. For the *why* behind a design choice, see the inline PHPDoc in the source; for spec-level rules, see the PSR documents themselves at [php-fig.org](https://www.php-fig.org/psr/). If something is unclear here, please [open a documentation issue](https://github.com/InitPHP/HTTP/issues/new) — these fixes are reviewed eagerly.
