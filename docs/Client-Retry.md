# Client Retry

The PSR-18 [`Client`](PSR-18-Client.md) is **single-attempt by default**. To retry *transient* failures automatically, attach a `RetryPolicy`. This page is the reference for the classes in `InitPHP\HTTP\Client\Retry`. The short version, with a usage example, is in [Client Configuration → Retries](Client-Configuration.md#retries).

```php
use InitPHP\HTTP\Client\Client;
use InitPHP\HTTP\Client\Retry\RetryPolicy;

$client = (new Client())->withRetryPolicy(new RetryPolicy(maxAttempts: 4));

$response = $client->get('https://api.example.com/reports');
```

## How a retry works

With a policy attached, `sendRequest()` runs this loop (the convenience methods such as `get()` and `post()` go through it too):

0. If the request must not be replayed (see [Which requests are retried](#which-requests-are-retried)), send it once and stop: the first response is returned, or the first exception thrown.
1. Send the request. This is attempt 1.
2. If the transport throws a `Psr\Http\Client\NetworkExceptionInterface` (DNS, TCP, TLS, timeout): retry when the policy has `retryOnException` on and another attempt is allowed. Otherwise the exception is re-thrown.
3. If a response arrives and its status is **not** in the retryable set, return it. This is also true for `4xx` and `5xx` responses: PSR-18 says they are returned, never thrown.
4. If its status is retryable and another attempt is allowed, wait, then go back to step 1. If no attempt is left, the last response is returned as-is.

The wait is the [`Backoff`](#backoff) delay for that attempt, or the `Retry-After` value when the policy honours it (see [`RetryAfter`](#retryafter)). Either way it never exceeds `maxDelay`.

### Which requests are retried

A retry sends the request again, so two kinds of request are **sent exactly once**, whatever the status or exception:

- **A method that is not in `retryableMethods`.** By default only the idempotent methods of RFC 9110 §9.2.2 are retried: `GET`, `HEAD`, `OPTIONS`, `TRACE`, `PUT` and `DELETE`. `POST` and `PATCH` are not, because the server may already have acted on a request that failed on the way back, and a retry would run it twice. To retry them anyway, list them in `retryableMethods` (see below), ideally together with an idempotency key the server honours.
- **A request whose body cannot be rewound.** Each attempt reads the body from the start. A stream that is not seekable (a socket, a pipe, a request body read once) is already used up after the first attempt, so a retry would send an **empty** body. Such a request is not retried; its first response is returned, or its first exception is thrown. Strings, `php://temp` and files are seekable and are retried normally.

Because `withRetryPolicy()` returns a clone, you can keep a retrying client for safe calls and a plain one for the rest.

Errors that are not transport failures are not retried: `RequestException` (a request that cannot be marshalled) and `ClientException` (for example, cURL cannot be initialised) propagate immediately. See [Client Exceptions](Client-Exceptions.md).

## `RetryPolicy`

`final class RetryPolicy` is an immutable description of *whether* and *how* to retry. Every argument has a default, so `new RetryPolicy()` is a working production policy.

```php
new RetryPolicy(
    int $maxAttempts = 3,
    float $baseDelay = 0.1,
    float $multiplier = 2.0,
    float $maxDelay = 30.0,
    float $jitter = 0.5,
    bool $retryOnException = true,
    ?array $retryableStatusCodes = null,   // null = RetryPolicy::DEFAULT_RETRYABLE_STATUS
    bool $respectRetryAfter = true,
    ?array $retryableMethods = null,       // null = RetryPolicy::DEFAULT_RETRYABLE_METHODS
)
```

| Parameter | Default | Meaning |
| --- | --- | --- |
| `maxAttempts` | `3` | **Total** attempts: the first try plus retries. `3` means at most 2 retries. Values below `1` are raised to `1`, which never retries. |
| `baseDelay` | `0.1` | Seconds to wait before the first retry. Negative values become `0`. |
| `multiplier` | `2.0` | Growth factor applied to the delay for each further retry. Values below `1.0` become `1.0`. |
| `maxDelay` | `30.0` | Cap, in seconds, on one backoff interval. Negative values become `0`. |
| `jitter` | `0.5` | Ratio in `[0.0, 1.0]`. Out-of-range values are clamped. `0.0` disables jitter. |
| `retryOnException` | `true` | Retry when the transport throws a network exception. |
| `retryableStatusCodes` | see below | Status codes that trigger a retry. Passing a list replaces the default set; it is not merged with it. |
| `respectRetryAfter` | `true` | Let a parseable `Retry-After` header on a retryable response set the wait, capped at `maxDelay`. |
| `retryableMethods` | see below | HTTP methods that may be retried, compared case-insensitively. Passing a list replaces the default set; it is not merged with it. An empty list never retries. |

The default retryable set is the constant `RetryPolicy::DEFAULT_RETRYABLE_STATUS`: `408`, `425`, `429`, `500`, `502`, `503` and `504`. The default methods are the constant `RetryPolicy::DEFAULT_RETRYABLE_METHODS`: `GET`, `HEAD`, `OPTIONS`, `TRACE`, `PUT` and `DELETE`.

```php
// Retry only on rate limiting and 503, and never on transport errors.
$policy = new RetryPolicy(
    maxAttempts: 5,
    retryOnException: false,
    retryableStatusCodes: [429, 503],
);

// Also retry POST. Only do this when the endpoint is idempotent, for example
// because it honours an Idempotency-Key header.
$policy = new RetryPolicy(
    retryableMethods: [...RetryPolicy::DEFAULT_RETRYABLE_METHODS, 'POST'],
);
```

### Methods

| Method | Returns | Description |
| --- | --- | --- |
| `getMaxAttempts()` | `int` | The (clamped) total number of attempts. |
| `getBaseDelay()` | `float` | The (clamped) base delay in seconds. |
| `getMultiplier()` | `float` | The (clamped) multiplier. |
| `getMaxDelay()` | `float` | The (clamped) maximum delay in seconds. |
| `getJitter()` | `float` | The (clamped) jitter ratio. |
| `isRetryOnException()` | `bool` | Whether network exceptions are retried. |
| `getRetryableStatusCodes()` | `list<int>` | The retryable status codes. |
| `getRetryableMethods()` | `list<string>` | The retryable methods, upper-cased and without duplicates. |
| `isRespectRetryAfter()` | `bool` | Whether `Retry-After` is honoured. |
| `isRetryableStatus(int $statusCode)` | `bool` | Whether the status is in the retryable set (strict comparison). |
| `isRetryableMethod(string $method)` | `bool` | Whether the method may be retried (case-insensitive). |
| `shouldRetry(int $attempt)` | `bool` | `true` when `$attempt < maxAttempts`. `$attempt` is 1-based: `1` means the first try has just finished. |

The policy has no setters. To change a value, build a new `RetryPolicy`.

## `Backoff`

`final class Backoff` computes the wait between attempts. The client builds one per `sendRequest()` call; you only need it to inspect or test a policy.

```php
new Backoff(RetryPolicy $policy, ?callable $randomizer = null)
```

`$randomizer` is a `callable(): float` that returns a value in `[0, 1)`. By default it is a `mt_rand()`-based uniform source. Values outside `[0, 1]` are clamped.

| Method | Returns | Description |
| --- | --- | --- |
| `baseDelayFor(int $attempt)` | `float` | The capped delay in seconds **without** jitter. |
| `delayFor(int $attempt)` | `float` | The delay in seconds **with** jitter, the one the client waits. |

`$attempt` is 1-based: `1` is the wait before the second try. Values below `1` are treated as `1`.

The base delay grows geometrically and is capped:

```text
baseDelayFor(n) = min(maxDelay, baseDelay * multiplier ** (n - 1))
```

Jitter then lowers it by a random amount of up to `jitter` of its value, so `delayFor(n)` always lies in `[base * (1 - jitter), base]`. With `jitter` at `0.0`, or a base delay of `0`, `delayFor()` returns the base delay unchanged. Spreading retries this way keeps a fleet of clients that failed at the same moment from retrying in lockstep.

For `baseDelay: 1.0, multiplier: 2.0, maxDelay: 5.0, jitter: 0.5`:

| Attempt | `baseDelayFor()` | `delayFor()` range |
| --- | --- | --- |
| 1 | `1.0` | `0.5` to `1.0` |
| 2 | `2.0` | `1.0` to `2.0` |
| 3 | `4.0` | `2.0` to `4.0` |
| 4 | `5.0` (capped) | `2.5` to `5.0` |

Pin the random source to get repeatable numbers:

```php
use InitPHP\HTTP\Client\Retry\{Backoff, RetryPolicy};

$policy  = new RetryPolicy(baseDelay: 1.0, multiplier: 2.0, maxDelay: 5.0, jitter: 0.5);
$backoff = new Backoff($policy, static fn (): float => 0.0);

$backoff->baseDelayFor(3); // 4.0
$backoff->delayFor(3);     // 2.0, the lower edge of the jitter range
```

## `RetryAfter`

`final class RetryAfter` reads the `Retry-After` response header (RFC 7231 §7.1.3). Both methods are static and return a delay in seconds as a `?float`; `null` means "no usable value", and the client then falls back to the backoff delay.

| Method | Description |
| --- | --- |
| `RetryAfter::fromResponse(ResponseInterface $response, ?int $now = null)` | Returns `null` when the header is absent; otherwise parses its value. |
| `RetryAfter::parse(string $value, ?int $now = null)` | Parses a raw header value. |

`$now` is the Unix timestamp used as "current time" for HTTP-dates and defaults to `time()`. It exists to make tests deterministic.

`parse()` follows these rules, in order:

1. The value is trimmed. An empty string gives `null`.
2. A value made only of digits is a number of seconds: `'120'` gives `120.0`.
3. Anything else goes to `strtotime()`. If that fails, the result is `null`. Otherwise the result is the difference to `$now`, never below `0`, so a date in the past gives `0.0`.

```php
use InitPHP\HTTP\Client\Retry\RetryAfter;

RetryAfter::parse('120');                                   // 120.0
RetryAfter::parse('Wed, 21 Oct 2015 07:28:00 GMT', 1445412420); // 60.0
RetryAfter::parse('');                                      // null
```

In the client, `Retry-After` applies to retryable **responses** only, not to network exceptions, and only when `respectRetryAfter` is `true`. When it applies, its value is not jittered but it **is capped by `maxDelay`**: a server that sends `Retry-After: 3600` with the default `maxDelay` of `30.0` makes the client wait 30 seconds, not an hour. Lower `maxDelay` to bound the wait further, or set `respectRetryAfter: false` to ignore the header and use the backoff delay.

## `SleeperInterface` and `Sleeper`

The client waits through a `SleeperInterface`, so tests do not have to wait for real time.

```php
interface SleeperInterface
{
    public function sleep(float $seconds): void;
}
```

Implementations must treat zero and negative values as a no-op. The default, `final class Sleeper`, does exactly that and otherwise calls `usleep()`, converting seconds to microseconds.

Replace it with `setSleeper()` (in place) or `withSleeper()` (a clone), for instance to record the requested waits in a test:

```php
use InitPHP\HTTP\Client\Client;
use InitPHP\HTTP\Client\Retry\{RetryPolicy, SleeperInterface};

final class RecordingSleeper implements SleeperInterface
{
    /** @var list<float> */
    public array $waits = [];

    public function sleep(float $seconds): void
    {
        $this->waits[] = $seconds;
    }
}

$sleeper = new RecordingSleeper();

$client = (new Client())
    ->withRetryPolicy(new RetryPolicy(maxAttempts: 3))
    ->withSleeper($sleeper)
    ->withJitterRandomizer(static fn (): float => 0.0); // repeatable jitter
```

`setJitterRandomizer(?callable)` and `withJitterRandomizer(?callable)` forward the random source to the `Backoff` the client creates; `null` restores the default. These two seams, together with `setSleeper()`/`withSleeper()`, exist mainly for tests.

## See also

- [Client Configuration → Retries](Client-Configuration.md#retries): the short guide, plus a hand-written retry loop for policies this one cannot express.
- [PSR-18 Client](PSR-18-Client.md): `sendRequest()` and the convenience methods.
- [Client Exceptions](Client-Exceptions.md): which failures are network exceptions.
- [API Reference](API-Reference.md): the `Client` methods for the retry policy, sleeper and jitter source.
