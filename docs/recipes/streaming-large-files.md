# Recipe: Streaming Large Files

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

1. The body Stream wraps the file's resource handle directly — no `file_get_contents()` into memory.
2. The emitter is given a 64 KiB buffer length, so the file is read and echoed in chunks.
3. Setting `Content-Length` explicitly lets the client show a progress bar and lets reverse proxies enable `sendfile` optimisations.

## Range support

Add it in one block (see also [Emitter — Content-Range](../emitter/content-range.md)):

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
    $status                  = 206;
    $headers['Content-Range']  = sprintf('bytes %d-%d/%d', $first, $last, $total);
    $headers['Content-Length'] = (string) ($last - $first + 1);
} else {
    $headers['Content-Length'] = (string) $total;
}

$response = (new Response($status, $headers))
    ->withBody(new Stream(fopen($path, 'rb')));

(new Emitter())->emit($response, 65536);
```

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

## Long-poll / Server-Sent Events

```php
$client = (new \InitPHP\HTTP\Client\Client())
    ->withTimeout(0)            // no overall timeout
    ->withConnectTimeout(5);    // but fail fast on connection
```

For SSE you'll typically also set `withFollowRedirects(false)` and read the response body chunk-by-chunk without ever calling `(string) $body`. The emitter side needs `flush()` after each emitted chunk, and any output buffering in front of it must be unwound (see [Chunked bodies](../emitter/chunked-bodies.md#disabling-output-buffering)).
