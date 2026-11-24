# Client Configuration

`InitPHP\HTTP\Client\Client` exposes five configuration knobs. Each has both a fluent `set*` mutator and an immutable `with*` wither.

| Setting          | Default                                       | Mutator                                           | Wither                                              |
|------------------|-----------------------------------------------|---------------------------------------------------|-----------------------------------------------------|
| Request timeout  | 30 s                                          | `setTimeout(int $seconds)`                        | `withTimeout(int $seconds)`                         |
| Connect timeout  | 10 s                                          | `setConnectTimeout(int $seconds)`                 | `withConnectTimeout(int $seconds)`                  |
| Follow redirects | `true` (up to 10 hops)                        | `setFollowRedirects(bool, int $max = 10)`         | `withFollowRedirects(bool, int $max = 10)`          |
| User-Agent       | `'InitPHP HTTP PSR-18 Client cURL'`           | `setUserAgent(?string)`                           | `withUserAgent(?string)`                            |
| Custom cURL opts | `[]`                                          | `setCurlOptions(array $options)`                  | `withCurlOptions(array $options)`                   |

```php
use InitPHP\HTTP\Client\Client;

$client = (new Client())
    ->withTimeout(15)
    ->withConnectTimeout(3)
    ->withFollowRedirects(true, 5)
    ->withUserAgent('my-service/1.0')
    ->withCurlOptions([
        CURLOPT_CAINFO         => '/etc/ssl/certs/ca-bundle.crt',
        CURLOPT_PROXY          => 'http://proxy.internal:3128',
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
```

## `set*` vs `with*`

`set*` **mutates the client in place** and returns `$this` for chaining:

```php
$client = new Client();
$client->setTimeout(15);     // $client now has timeout=15
```

`with*` **returns a clone**:

```php
$client     = new Client();
$shortClient = $client->withTimeout(5);
// $client still has timeout=30; $shortClient has timeout=5
```

**Pick the wither** when the client is shared across many request paths and each path needs its own override. **Pick the mutator** when you're building the client once at boot.

## Timeouts

`CURLOPT_TIMEOUT = 0` disables the timeout entirely. The package's **default of 30 s** exists because a runaway request will tie up a PHP-FPM worker indefinitely otherwise — production HTTP clients without a timeout are an outage waiting to happen.

If you really need an infinite wait (long-poll, server-sent events), be explicit:

```php
$longPoll = $client->withTimeout(0)->withConnectTimeout(10);
```

The connect timeout is separate from the total timeout. `CURLOPT_CONNECTTIMEOUT = 10` means we'll spend at most 10 s establishing the TCP/TLS handshake before failing fast; the remaining time budget is reserved for the response.

## Redirects

```php
// default — follow up to 10 redirects
$client->withFollowRedirects(true);

// follow up to 3 redirects
$client->withFollowRedirects(true, 3);

// don't follow at all (caller handles 3xx)
$client->withFollowRedirects(false);
```

When following is on, the returned `Response` is the **final** leg only — intermediate responses are not exposed.

## User-Agent

```php
$client->setUserAgent('my-service/1.0 (+https://example.com/contact)');
```

Setting `null` or the empty string is a **no-op** — the User-Agent is never cleared by these methods; the configured (or default) value is what is sent when the request itself carries no `User-Agent` header. If you really need an empty `User-Agent`, do it at the request level (`$request->withHeader('User-Agent', '')`) which is what gets emitted to the wire.

## Custom cURL options — the escape hatch

`curlOptions` are merged on top of the per-request defaults (URL, headers, body, timeouts, ...) **last-write-wins**. So an override here wins over the explicit knobs above; reach for this sparingly.

Useful patterns:

```php
// Force SNI for self-signed cert testing
[CURLOPT_RESOLVE => ['api.example.test:443:127.0.0.1']]

// Pin TLS version
[CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_3]

// Capture verbose debug output
[CURLOPT_VERBOSE => true, CURLOPT_STDERR => fopen('php://stderr', 'w')]

// Bind to a specific local interface
[CURLOPT_INTERFACE => '203.0.113.10']

// HTTP/3 (if your cURL build supports it)
[CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_3]
```

## Retries

The client is **single-attempt by default**: PSR-18 leaves retry policy to the caller, so without a policy a failed request is not repeated. To opt in to automatic retries of *transient* failures, attach an `InitPHP\HTTP\Client\Retry\RetryPolicy` with `withRetryPolicy()` (or `setRetryPolicy()`; `getRetryPolicy()` returns the current one):

```php
use InitPHP\HTTP\Client\Client;
use InitPHP\HTTP\Client\Retry\RetryPolicy;

$client = (new Client())->withRetryPolicy(new RetryPolicy(
    maxAttempts: 4,    // 1 initial try + up to 3 retries
    baseDelay:   0.1,  // first backoff interval, seconds
    multiplier:  2.0,  // 0.1s, 0.2s, 0.4s, ...
    maxDelay:    30.0, // cap on any single interval
    jitter:      0.5,  // up to 50% random reduction
));
```

- Retried by default: transport exceptions (`retryOnException`) and the status codes `408`, `425`, `429`, `500`, `502`, `503` and `504` (`retryableStatusCodes`).
- Only **idempotent methods** are retried by default: `GET`, `HEAD`, `OPTIONS`, `TRACE`, `PUT` and `DELETE`. A `POST` or `PATCH` is sent once; list it in `retryableMethods` to opt in.
- A request whose body is not seekable is sent once too, because a retry could not send the body again.
- A parseable `Retry-After` header on a retryable response overrides the computed backoff, up to `maxDelay`, unless `respectRetryAfter` is `false`.
- A non-retryable `4xx`/`5xx` response is still **returned, never thrown**. A retryable response that exhausts `maxAttempts` is returned as-is; a transport exception that exhausts it is re-thrown.

The full reference (every `RetryPolicy` parameter and clamp, the backoff formula, `Retry-After` parsing and the sleeper seam) is on the [Client Retry](Client-Retry.md) page.

Need a different policy (idempotency keys, circuit breakers, ...)? Wrap the client in your own loop instead:

```php
function retry(\Psr\Http\Client\ClientInterface $client, RequestInterface $request, int $tries = 3, int $delayMs = 500): ResponseInterface
{
    $last = null;
    for ($i = 0; $i < $tries; $i++) {
        try {
            $response = $client->sendRequest($request);
            if ($response->getStatusCode() < 500) {
                return $response;
            }
            $last = new RuntimeException('upstream ' . $response->getStatusCode());
        } catch (\Psr\Http\Client\NetworkExceptionInterface $e) {
            $last = $e;
        }
        usleep($delayMs * 1000 * (2 ** $i));
    }
    throw $last;
}
```

## See also

- [Client](PSR-18-Client.md) — the surface this configures.
- [Client Exceptions](Client-Exceptions.md) — what failures look like.
