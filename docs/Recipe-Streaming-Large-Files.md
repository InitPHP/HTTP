# Recipe — Streaming Large Files

## Sending a file as a response

```php
use InitPHP\HTTP\Message\Response;
use InitPHP\HTTP\Message\Stream;
use InitPHP\HTTP\Emitter\Emitter;

$path = '/var/files/report.pdf';

$response = (new Response(200, [
    'Content-Type'        => 'application/pdf',
    'Content-Length'      => (string) filesize($path),
    'Content-Disposition' => 'attachment; filename="report.pdf"',
]))->withBody(new Stream(fopen($path, 'rb')));

(new Emitter())->emit($response, /* bufferLength: */ 65536);
```

Three things make this efficient:

1. The body `Stream` wraps the file's resource handle directly — no `file_get_contents()` into memory.
2. The emitter is given a 64 KiB buffer length, so the file is read and `echo`-ed in chunks.
3. Setting `Content-Length` explicitly lets the client show a progress bar and lets reverse proxies enable `sendfile` optimisations.

## With byte-range support

Add it in one block (see also [Content-Range](Content-Range.md)):

```php
$total = filesize($path);
$range = $request->getHeaderLine('Range');

$status  = 200;
$headers = [
    'Content-Type'  => 'application/pdf',
    'Accept-Ranges' => 'bytes',
];

if (preg_match('/^bytes=(\d+)-(\d*)$/', $range, $m)) {
    $first = (int) $m[1];
    $last  = $m[2] !== '' ? (int) $m[2] : $total - 1;
    $status                    = 206;
    $headers['Content-Range']  = sprintf('bytes %d-%d/%d', $first, $last, $total);
    $headers['Content-Length'] = (string) ($last - $first + 1);
} else {
    $headers['Content-Length'] = (string) $total;
}

$response = (new Response($status, $headers))
    ->withBody(new Stream(fopen($path, 'rb')));

(new Emitter())->emit($response, 65536);
```

`Accept-Ranges: bytes` on the **first** 200 response tells the client it can negotiate ranges next time around — important for `<video>` and `<audio>` seeking.

## Receiving a large response

Don't `(string) $response->getBody()` it into memory; read it incrementally:

```php
$client   = new \InitPHP\HTTP\Client\Client();
$response = $client->sendRequest(new \InitPHP\HTTP\Message\Request('GET', $url));

$out = fopen('/tmp/big.bin', 'wb');
$body = $response->getBody();
while (!$body->eof()) {
    fwrite($out, $body->read(65536));
}
fclose($out);
```

The PSR-18 response body in this package is backed by `php://temp` (small payloads stay in memory; larger ones spill to disk), so even before you start reading you're not paying RAM for the whole response.

## Disabling the request timeout for long downloads

The Client's 30 s default timeout includes the response body transfer. For multi-GB downloads, raise it — or disable it for that specific client instance:

```php
$client = (new \InitPHP\HTTP\Client\Client())
    ->withTimeout(0)            // no overall timeout
    ->withConnectTimeout(10);   // but still fail fast on connection
```

## Long-poll / Server-Sent Events caveat

SSE looks like a streaming HTTP response but isn't a great fit for the PSR-7 emitter abstraction — the emitter's chunked path assumes a *bounded* body that ends eventually. For genuinely unbounded push streams (SSE, log tail, queue consumers), `echo` the frames yourself and `flush()` directly inside your event loop, with reverse-proxy buffering turned off:

```nginx
location /events {
    proxy_buffering off;
    gzip off;
}
```

## See also

- [Emitter — Chunked Bodies](Chunked-Bodies.md) — `bufferLength` tuning.
- [Content-Range](Content-Range.md) — partial-content emission.
- [Recipe — File Upload](Recipe-File-Upload.md) — the mirror direction.
