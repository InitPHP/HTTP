# PSR-18 Exceptions

PSR-18 distinguishes three failure shapes, each represented by an interface in `Psr\Http\Client\`:

| Interface                           | When the client throws it                                                                 |
|-------------------------------------|------------------------------------------------------------------------------------------|
| `ClientExceptionInterface`          | Parent of the two below; also raised on infrastructure failure (cURL won't initialise).  |
| `RequestExceptionInterface`         | The supplied `RequestInterface` is malformed — the request never went on the wire.        |
| `NetworkExceptionInterface`         | A transport-level failure prevented the response from arriving (DNS, TCP, TLS, timeout). |

This package's concrete classes:

```
InitPHP\HTTP\Client\Exceptions\ClientException     implements ClientExceptionInterface
InitPHP\HTTP\Client\Exceptions\RequestException    extends ClientException implements RequestExceptionInterface
InitPHP\HTTP\Client\Exceptions\NetworkException    extends ClientException implements NetworkExceptionInterface
```

Both `RequestException` and `NetworkException` carry the originating request — fetch it with `->getRequest()`.

## What is NOT an exception

**4xx and 5xx responses are NOT exceptions** under PSR-18. The client returns them as a regular `ResponseInterface`. If you want to throw on a 4xx, do it explicitly:

```php
$response = $client->sendRequest($request);

if ($response->getStatusCode() >= 400) {
    throw new \DomainException(
        'Upstream returned ' . $response->getStatusCode() . ': ' . (string) $response->getBody()
    );
}
```

## Catch surface

Use the **interfaces**, not the concrete classes, so substituting another PSR-18 client later doesn't require touching the `catch` blocks:

```php
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Client\ClientExceptionInterface;

try {
    $response = $client->sendRequest($request);
} catch (RequestExceptionInterface $e) {
    // Bad request shape — don't retry as-is.
    $logger->error('Malformed outbound request', ['err' => $e->getMessage()]);
    throw $e;
} catch (NetworkExceptionInterface $e) {
    // Transient — caller may retry with backoff.
    $logger->warning('Upstream unreachable', ['err' => $e->getMessage()]);
    throw $e;
} catch (ClientExceptionInterface $e) {
    // ext-curl missing, cURL init failed — infrastructure.
    $logger->critical('Client setup failure', ['err' => $e->getMessage()]);
    throw $e;
}
```

## Mapping in this client

| Failure                                        | Concrete exception                          |
|-----------------------------------------------|---------------------------------------------|
| `ext-curl` is not loaded                      | `ClientException` (in the constructor)      |
| `curl_init()` returned `false`                | `ClientException`                           |
| `Request::getUri()` produced an invalid URL   | `RequestException` (request never sent)     |
| `Request::getBody()->getContents()` threw     | `RequestException` (request never sent)     |
| `curl_exec()` returned `false` for any reason | `NetworkException` carrying the cURL error  |

The `NetworkException` constructor wraps `curl_error()` (or a fallback "cURL error" message) and stores `(int) curl_errno()` in `getCode()`. Match on the code if you need to distinguish DNS failure (6) from TLS errors (35, 60) from timeouts (28).
