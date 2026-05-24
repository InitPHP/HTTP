# Emitter — Content-Range

When a response carries `Content-Range: bytes <first>-<last>/<total|*>`, the chunked emit path honours the range automatically:

```php
$range = 'bytes 1024-2047/4096';
$response = (new Response(206))
    ->withHeader('Content-Range', $range)
    ->withHeader('Content-Type', 'application/octet-stream');

$response->getBody()->write($fullPayload);

(new Emitter())->emit($response, /* bufferLength: */ 8192);
```

Under the hood:

1. `Content-Range` is parsed into `unit`, `first`, `last`, `length`.
2. If `unit` is `bytes`, the body is seeked to `first` (when seekable) and read in `$bufferLength`-byte chunks until `last - first + 1` bytes have been emitted.
3. If `unit` is anything else (or the header is absent / malformed), the emitter falls back to a regular rewind-and-stream.

This means you can serve byte ranges to clients like:

```bash
curl -H 'Range: bytes=1024-2047' http://localhost/big.bin
```

…by computing the range on the request side, setting `Content-Range` on the response, and letting the emitter slice the body for you.

## Full example: a tiny static-file responder

```php
function serveFile(string $path, ServerRequestInterface $request): ResponseInterface
{
    $total  = filesize($path);
    $body   = (new \InitPHP\HTTP\Message\Stream(fopen($path, 'rb')));
    $headers = ['Content-Type' => mime_content_type($path) ?: 'application/octet-stream'];

    $range = $request->getHeaderLine('Range');
    if (preg_match('/^bytes=(\d+)-(\d*)$/', $range, $m)) {
        $first = (int) $m[1];
        $last  = $m[2] !== '' ? (int) $m[2] : $total - 1;
        $headers['Content-Range']  = sprintf('bytes %d-%d/%d', $first, $last, $total);
        $headers['Content-Length'] = (string) ($last - $first + 1);
        return (new \InitPHP\HTTP\Message\Response(206, $headers, $body));
    }

    $headers['Content-Length'] = (string) $total;
    return new \InitPHP\HTTP\Message\Response(200, $headers, $body);
}

(new \InitPHP\HTTP\Emitter\Emitter())->emit(serveFile('/var/files/big.bin', $request), 65536);
```

For media-class workloads (audio/video seeking) you'll also want to set `Accept-Ranges: bytes` on the **first** 200 response so the client knows it can negotiate ranges next time.
