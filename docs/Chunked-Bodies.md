# Emitter — Chunked Bodies

`Emitter::emit()` accepts an optional second argument that switches the default "echo the whole body in one go" path into a chunked stream:

```php
use InitPHP\HTTP\Emitter\Emitter;

$emitter = new Emitter();
$emitter->emit($response, /* bufferLength: */ 8192);
```

With a non-null, non-zero `$bufferLength`:

1. The output buffer is flushed up front (`flush()`).
2. If the response carries a `Content-Range: bytes ...` header, only the requested byte range is emitted — see [Content-Range](Content-Range.md).
3. Otherwise the body is rewound (if seekable) and read in `$bufferLength`-byte chunks until EOF, echoing each chunk as it arrives.

This avoids materialising the entire body as a single PHP string — critical for multi-megabyte file downloads and streaming JSON.

## Picking a buffer length

There's no universally right answer:

| Use case                  | Suggested `bufferLength`             |
|---------------------------|--------------------------------------|
| Small dynamic responses   | `null` (default — single echo)       |
| Static file downloads     | 8192 – 65536 (8 KiB – 64 KiB)        |
| Long-poll / SSE           | 1024 (tight latency)                 |
| Backed by a slow network  | match downstream MTU minus headroom  |

Cost of "too small" = more write syscalls; cost of "too big" = extra memory residence per chunk. **8–64 KiB is a safe default for almost everything.**

## Disabling output buffering

`flush()` only does what you expect if PHP's output buffer is empty (or short enough that `ob_flush()` was called for you). If you've wrapped your application in `ob_start()` and you want chunked emission to actually reach the browser before the script finishes, unwind the buffers first:

```php
while (ob_get_level() > 0) {
    ob_end_flush();
}

$emitter->emit($response, 8192);
```

For long-running streams (SSE, log tail) you'll also want to disable nginx's `gzip` and `proxy_buffering` for the relevant location:

```
location /events {
    proxy_buffering off;
    gzip off;
}
```

That's a reverse-proxy concern, not an emitter knob — but it's the missing piece that confuses most "why isn't my SSE flowing?" cases.

## Examples

### Streaming a 64 KiB-chunked file download

```php
$path = '/var/files/report.pdf';

$response = (new \InitPHP\HTTP\Message\Response(200, [
    'Content-Type'        => 'application/pdf',
    'Content-Length'      => (string) filesize($path),
    'Content-Disposition' => 'attachment; filename="report.pdf"',
]))->withBody(new \InitPHP\HTTP\Message\Stream(fopen($path, 'rb')));

(new Emitter())->emit($response, 65536);
```

### Server-Sent Events

```php
// Build a response whose body is a never-ending PHP generator-style write.
// In practice you'd `echo "data: ...\n\n"` and `flush()` directly inside
// your event loop rather than relying on the emitter; the chunked emit
// path is for *response-shaped* large bodies, not push streams.
```

If your data source is genuinely push (event loop, queue consumer), don't use the emitter at all — write the SSE frames to stdout and `flush()` directly. The PSR-7/Emitter abstraction shines for *bounded* large bodies (files, generated reports), not unbounded push streams.

## See also

- [Emitter](Emitter.md) — strict mode, exception types.
- [Content-Range](Content-Range.md) — chunked emit + byte-range filter.
- [Recipe — Streaming Large Files](Recipe-Streaming-Large-Files.md) — end-to-end file download recipe with range support.
