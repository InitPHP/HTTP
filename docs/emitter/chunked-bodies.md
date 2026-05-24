# Emitter — Chunked Bodies

`Emitter::emit()` accepts an optional second argument that turns the default "echo the whole body" into a chunked stream:

```php
$emitter = new Emitter();
$emitter->emit($response, /* bufferLength: */ 8192);
```

With a non-null, non-zero `$bufferLength`:

1. The output buffer is flushed up front (`flush()`).
2. If the response carries a `Content-Range: bytes ...` header, only the requested byte range is emitted (see [Content-Range](content-range.md)).
3. Otherwise the body is rewound (if seekable) and read in `$bufferLength`-byte chunks until EOF, echoing each chunk as it arrives.

This avoids materialising the entire body as a single PHP string — critical for multi-megabyte file downloads or streaming JSON.

## Picking a buffer length

There's no universally right answer, but:

| Use case                 | Suggested `bufferLength`             |
|--------------------------|--------------------------------------|
| Small dynamic responses  | `null` (default — single echo)       |
| Static file downloads    | 8192 — 65536 (8 KiB – 64 KiB)        |
| Long-poll / SSE          | 1024 (tight latency)                 |
| Backed by a slow network | match downstream MTU minus headroom  |

The cost of "too small" is more `write()` syscalls; the cost of "too big" is extra memory residence per chunk. 8–64 KiB is a safe default for almost everything.

## Disabling output buffering

`flush()` only works if PHP's output buffer is empty (or short enough that `ob_flush()` was called for you). If you've wrapped your application in `ob_start()` and want chunked emission to actually reach the browser, close the buffer first:

```php
while (ob_get_level() > 0) {
    ob_end_flush();
}

$emitter->emit($response, 8192);
```

For long-running streams (SSE, log tail) you also want to disable nginx's `gzip` and `proxy_buffering` for the location — that's an upstream concern, not the emitter's.
