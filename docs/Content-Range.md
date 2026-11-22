# Emitter — Content-Range

When a response carries `Content-Range: bytes <first>-<last>/<total|*>`, the chunked emit path honours the range automatically:

```php
use InitPHP\HTTP\Emitter\Emitter;
use InitPHP\HTTP\Message\Response;

$response = (new Response(206))
    ->withHeader('Content-Range', 'bytes 1024-2047/4096')
    ->withHeader('Content-Type', 'application/octet-stream');

$response->getBody()->write($fullPayload);

(new Emitter())->emit($response, /* bufferLength: */ 8192);
```

The emitter:

1. Parses `Content-Range` into `unit`, `first`, `last`, `length`.
2. If `unit` is `bytes`, seeks the body to `first` (when seekable) and reads in `$bufferLength`-byte chunks until `last - first + 1` bytes have been emitted.
3. If `unit` is anything else (or the header is absent / malformed), falls back to a regular rewind-and-stream.

The chunked path is required — `emit($response)` without a `bufferLength` echoes the entire body and ignores `Content-Range`.

## Full recipe: a tiny static-file responder

```php
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use InitPHP\HTTP\Message\Response;
use InitPHP\HTTP\Message\Stream;
use InitPHP\HTTP\Emitter\Emitter;

function serveFile(string $path, ServerRequestInterface $request): ResponseInterface
{
    $total = filesize($path);

    $headers = [
        'Content-Type'  => mime_content_type($path) ?: 'application/octet-stream',
        'Accept-Ranges' => 'bytes',
    ];

    $body = new Stream(fopen($path, 'rb'));

    $range = $request->getHeaderLine('Range');
    if (preg_match('/^bytes=(\d+)-(\d*)$/', $range, $m)) {
        $first = (int) $m[1];
        $last  = $m[2] !== '' ? (int) $m[2] : $total - 1;
        $headers['Content-Range']  = sprintf('bytes %d-%d/%d', $first, $last, $total);
        $headers['Content-Length'] = (string) ($last - $first + 1);
        return new Response(206, $headers, $body);
    }

    $headers['Content-Length'] = (string) $total;
    return new Response(200, $headers, $body);
}

(new Emitter())->emit(serveFile('/var/files/big.bin', $request), 65536);
```

`Accept-Ranges: bytes` on the **first** 200 response tells the client it can negotiate ranges next time around. That's important for media-class workloads — `<video>` and `<audio>` elements seek by re-requesting byte ranges of the same URL.

## Multi-range responses

Multi-range responses (`Content-Range: multipart/byteranges; boundary=...`) are **not** handled by this emitter — only the single-range form. Multi-range is rare in practice and adds significant boilerplate; if you need it, build the multipart body yourself and emit with the default (non-chunked) path.

## See also

- [Chunked Bodies](Chunked-Bodies.md) — `bufferLength` tuning.
- [Recipe — Streaming Large Files](Recipe-Streaming-Large-Files.md) — fuller file-download walk-through.
