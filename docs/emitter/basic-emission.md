# Emitter — Basics

`InitPHP\HTTP\Emitter\Emitter` converts a PSR-7 `ResponseInterface` into bytes on the wire under any SAPI that exposes `header()` and stdout.

```php
use InitPHP\HTTP\Emitter\Emitter;

(new Emitter())->emit($response);
```

That's everything for the common case. Three things happen, in order:

1. **Output sanity check** (strict mode only). If anything has already been written or `header()` already called, the emitter throws — better than silently sending a corrupt response.
2. **Status line** — `header("HTTP/1.1 200 OK", true, 200)`.
3. **Headers** — every entry from `$response->getHeaders()` becomes a `header()` call. The key is normalised to title-case-with-dash (`x-trace-id` → `X-Trace-Id`); `Set-Cookie` is the one special case that's allowed to repeat.
4. **Body** — the whole body is `echo`-ed in one go, or streamed in chunks if you pass a buffer length.

## Strict mode

```php
$emitter = new Emitter(/* strictMode: */ true);  // default
```

When strict mode is on:

- `headers_sent($file, $line)` is checked first; if `true`, the emitter throws `EmitHeaderException` naming the file and line that flushed.
- The active output buffer (if any) is inspected; non-empty buffers raise `EmitBodyException`.

Turn strict mode off only when you're integrating with something that already wrote partial output (e.g. a legacy script that `echo`-ed before passing control to your framework).

```php
$emitter = new Emitter(/* strictMode: */ false);
```

## When to use each exception

| Exception              | Cause                                       | Typical fix                                      |
|------------------------|---------------------------------------------|--------------------------------------------------|
| `EmitHeaderException`  | `headers_sent()` returned true              | Find the early-`echo` / BOM / un-suppressed warning |
| `EmitBodyException`    | Output buffer has un-flushed content        | `ob_clean()` before emit, or unwind the layer that started buffering |

Both extend `\RuntimeException`, so a single catch is fine if you don't care which side triggered:

```php
try {
    (new Emitter())->emit($response);
} catch (\RuntimeException $e) {
    error_log($e->getMessage());
}
```

## See also

- [Chunked bodies](chunked-bodies.md) for large responses.
- [Content-Range](content-range.md) for serving partial content / byte ranges.
