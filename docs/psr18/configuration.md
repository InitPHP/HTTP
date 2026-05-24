# PSR-18 Client Configuration

The `Client` class exposes a small set of knobs. Each has both a fluent `set*` mutator and an immutable `with*` wither.

| Setting           | Default | Mutator                                       | Wither                                          |
|-------------------|---------|-----------------------------------------------|-------------------------------------------------|
| Request timeout   | 30 s    | `setTimeout(int $seconds)`                    | `withTimeout(int $seconds)`                     |
| Connect timeout   | 10 s    | `setConnectTimeout(int $seconds)`             | `withConnectTimeout(int $seconds)`              |
| Follow redirects  | `true`  | `setFollowRedirects(bool, int $max = 10)`     | `withFollowRedirects(bool, int $max = 10)`      |
| User-Agent        | `'InitPHP HTTP PSR-18 Client cURL'` | `setUserAgent(?string)` | `withUserAgent(?string)`                        |
| Custom cURL opts  | `[]`    | `setCurlOptions(array $options)`              | `withCurlOptions(array $options)`               |

```php
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

## Setting `setUserAgent(null)` or `''`

Both no-op. The User-Agent is never *cleared* — the constant default is what's sent if the request itself didn't carry a `User-Agent` header. If you need an empty UA explicitly, do it at the request level (`$request = $request->withHeader('User-Agent', '')`).

## Custom cURL options take last priority

`curlOptions` is merged on top of the defaults computed from the message + the timeout/redirect settings. Last-write-wins: if you set `CURLOPT_FOLLOWLOCATION` in `withCurlOptions`, that wins over `setFollowRedirects`. Use this escape hatch sparingly; prefer the explicit knobs.

Useful overrides you might reach for:

```php
// Force SNI hostname for self-signed cert testing
[CURLOPT_RESOLVE => ['api.example.test:443:127.0.0.1']]

// Pin TLS version
[CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_3]

// Capture verbose debug output
[CURLOPT_VERBOSE => true, CURLOPT_STDERR => fopen('php://stderr', 'w')]

// Bind to a specific interface (server with multiple IPs)
[CURLOPT_INTERFACE => '203.0.113.10']
```

## Mutator vs wither

`set*` mutates the client in place and returns `$this` for chaining:

```php
$client = new Client();
$client->setTimeout(15);   // $client now has timeout=15
```

`with*` returns a clone:

```php
$client     = new Client();
$shortClient = $client->withTimeout(5);
// $client still has timeout=30; $shortClient has timeout=5
```

Use the wither when the client is shared across request paths and each path needs its own override. Use the mutator when you're building the client once at boot.

## Timeouts

`CURLOPT_TIMEOUT = 0` disables the timeout entirely. The default of 30 s exists because a runaway request will tie up an FPM worker indefinitely otherwise — production HTTP clients without a timeout are an outage waiting to happen. If you really need infinite wait (long-poll, server-sent events), be explicit:

```php
$longPoll = $client->withTimeout(0)->withConnectTimeout(10);
```

The connect timeout is separate from the total timeout. `CURLOPT_CONNECTTIMEOUT = 10` means we'll spend at most 10 s establishing the TCP/TLS handshake before failing fast; the remaining time budget is reserved for the response.
