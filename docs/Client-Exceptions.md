# Client Exceptions

PSR-18 distinguishes three failure shapes, each modelled by an interface in `Psr\Http\Client\`:

| Interface                           | When the client throws it                                                                  |
|-------------------------------------|--------------------------------------------------------------------------------------------|
| `ClientExceptionInterface`          | Parent of the two below; also raised on infrastructure failure (cURL won't initialise, ...). |
| `RequestExceptionInterface`         | The supplied `RequestInterface` is malformed — the request never went on the wire.          |
| `NetworkExceptionInterface`         | A transport-level failure prevented the response from arriving (DNS, TCP, TLS, timeout).    |

This package's concrete classes:

```
InitPHP\HTTP\Client\Exceptions\ClientException   implements Psr\Http\Client\ClientExceptionInterface
InitPHP\HTTP\Client\Exceptions\RequestException  extends   ClientException
                                                  implements Psr\Http\Client\RequestExceptionInterface
InitPHP\HTTP\Client\Exceptions\NetworkException  extends   ClientException
                                                  implements Psr\Http\Client\NetworkExceptionInterface
```

Both `RequestException` and `NetworkException` carry the originating request — call `->getRequest()` to retrieve it.

## What is **not** an exception

**4xx and 5xx responses are not exceptions** under PSR-18. The client returns them as a regular `ResponseInterface`. If you want to throw on a 4xx/5xx, do it explicitly:

```php
$response = $client->sendRequest($request);

if ($response->getStatusCode() >= 400) {
    throw new \DomainException(
        'Upstream returned ' . $response->getStatusCode() . ': ' . (string) $response->getBody()
    );
}
```

This is what makes PSR-18 swappable: every conforming client follows the same rule, so your `catch` blocks only have to consider transport failures.

## Catching by interface

Use the **interfaces**, not the concrete classes, so substituting another PSR-18 client later doesn't require touching the `catch` blocks:

```php
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Client\ClientExceptionInterface;

try {
    $response = $client->sendRequest($request);
} catch (RequestExceptionInterface $e) {
    // The request was malformed — don't retry as-is.
    $logger->error('Malformed outbound request', ['err' => $e->getMessage()]);
    throw $e;
} catch (NetworkExceptionInterface $e) {
    // Transient — caller may retry with backoff.
    $logger->warning('Upstream unreachable', ['err' => $e->getMessage()]);
    throw $e;
} catch (ClientExceptionInterface $e) {
    // Infrastructure (ext-curl missing, cURL init failed, ...).
    $logger->critical('Client setup failure', ['err' => $e->getMessage()]);
    throw $e;
}
```

## Mapping in this client

| Failure                                                          | Concrete exception                            |
|------------------------------------------------------------------|-----------------------------------------------|
| `ext-curl` is not loaded                                         | `ClientException` (raised by the constructor) |
| `curl_init()` returned `false`                                    | `ClientException`                             |
| `Request::getUri()` produced an invalid URL (filter_var fails)    | `RequestException` (request never sent)       |
| Reading the request body via `getBody()->getContents()` threw     | `RequestException` (request never sent)       |
| `curl_exec()` returned `false` for any reason                     | `NetworkException` (carries the cURL error)   |

The `NetworkException` constructor wraps `curl_error()` (or a fallback `'cURL error'` message) and stores `(int) curl_errno()` in `getCode()`. Match on the code if you need to distinguish DNS failure (`6`) from TLS errors (`35`, `60`) from timeouts (`28`).

```php
catch (NetworkExceptionInterface $e) {
    if ($e->getCode() === 28) {
        // Timeout — usually safe to retry
        return $this->retry($request);
    }
    throw $e;
}
```

## Accessing the request from an exception

```php
catch (NetworkExceptionInterface $e) {
    $request = $e->getRequest();
    error_log(sprintf(
        '%s %s failed: %s (cURL #%d)',
        $request->getMethod(),
        (string) $request->getUri(),
        $e->getMessage(),
        $e->getCode()
    ));
    throw $e;
}
```

## See also

- [Client](PSR-18-Client.md) — the entry point and high-level helpers.
- [Configuration](Client-Configuration.md) — timeouts and other knobs that affect which exception type you see.
