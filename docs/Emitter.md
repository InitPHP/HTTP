# Emitter — Basic Emission

`InitPHP\HTTP\Emitter\Emitter` converts a PSR-7 `ResponseInterface` into bytes on the wire under any SAPI that exposes `header()` and stdout — PHP-FPM, mod_php, the built-in CLI server, FrankenPHP.

```php
use InitPHP\HTTP\Emitter\Emitter;

(new Emitter())->emit($response);
```

That's everything for the common case. The `emit()` call does these things in order:

1. **Output sanity check** (strict mode only). If anything has already been written or `header()` has already been called, the emitter throws — better than silently sending a corrupt response.
2. **Status line.** `header("HTTP/1.1 200 OK", true, 200)`.
3. **Headers.** Every entry from `$response->getHeaders()` becomes a `header()` call. Names are normalised to title-case-with-dash (`x-trace-id` → `X-Trace-Id`); `Set-Cookie` is the one allowed-to-repeat header (subsequent values use `replace = false`).
4. **Body.** The whole body is `echo`-ed in one go, or streamed in chunks if you pass a buffer length.

## Strict mode

```php
$emitter = new Emitter(/* strictMode: */ true);   // default
```

When strict mode is on:

- `headers_sent($file, $line)` is checked first; if `true`, the emitter throws `EmitHeaderException` naming the file and line that flushed prematurely.
- The active output buffer (if any) is inspected; non-empty buffers raise `EmitBodyException`.

Turn strict mode off only when you're integrating with something that already wrote partial output (e.g. a legacy script that `echo`-ed before passing control to your framework):

```php
$emitter = new Emitter(/* strictMode: */ false);
$emitter->emit($response);
```

## Two exception types, two failure modes

```php
namespace InitPHP\HTTP\Emitter\Exceptions;

class EmitHeaderException extends \RuntimeException { ... }
class EmitBodyException   extends \RuntimeException { ... }
```

| Exception              | Cause                                                                | Typical fix                                                |
|------------------------|----------------------------------------------------------------------|------------------------------------------------------------|
| `EmitHeaderException`  | `headers_sent()` returned true                                       | Find the early `echo` / BOM / un-suppressed warning        |
| `EmitBodyException`    | Output buffer has un-flushed content                                 | `ob_clean()` before emit, or unwind the buffering layer    |

Both extend `\RuntimeException`, so a single catch is fine if you don't care which side fired:

```php
try {
    (new Emitter())->emit($response);
} catch (\RuntimeException $e) {
    error_log($e->getMessage());
}
```

> **v2 → v3 note:** v2 always raised `EmitBodyException` for both failure modes, so callers couldn't distinguish "headers already flushed" from "output buffer dirty". v3 splits the two.

## Body emission

The default body emission `echo`-es the body in one go, after calling `__toString()` (which rewinds the stream if seekable):

```php
$emitter->emit($response);
```

That's fine for small responses. For multi-MB bodies, hand `emit()` a chunk size:

```php
$emitter->emit($response, /* bufferLength: */ 8192);
```

This switches to a chunked path that streams the body in `bufferLength`-byte slices — covered in [Chunked Bodies](Chunked-Bodies.md).

## Honouring `Content-Range`

When the response carries a `Content-Range: bytes <first>-<last>/<total|*>` header and you're using the chunked path, only the requested range is emitted — covered in [Content-Range](Content-Range.md).

## Static facade

The facade lazily resolves a shared `Emitter` instance:

```php
use InitPHP\HTTP\Facade\Emitter;
Emitter::emit($response);
Emitter::emit($response, 8192);
```

See [Facades](Facades.md).

## See also

- [Chunked Bodies](Chunked-Bodies.md) — `bufferLength` tuning for large responses.
- [Content-Range](Content-Range.md) — partial-content / byte-range responses.
- [Recipe — Streaming Large Files](Recipe-Streaming-Large-Files.md) — file download patterns end-to-end.
