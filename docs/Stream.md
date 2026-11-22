# Stream

`InitPHP\HTTP\Message\Stream` is the package's PSR-7 `StreamInterface` implementation. It powers every message body and the response body the PSR-18 [Client](PSR-18-Client.md) hands back.

```php
use InitPHP\HTTP\Message\Stream;
```

## Construction

```php
new Stream($body = '', ?string $target = null);
```

Four input shapes are accepted for `$body`:

| `$body`                  | What it means                                                          |
|--------------------------|------------------------------------------------------------------------|
| `string`                 | Seeded literal bytes (most common).                                    |
| `resource`               | An already-open PHP stream handle, used directly.                      |
| `Psr\Http\Message\StreamInterface` | Contents are read and re-staged on the chosen backend.        |
| `null`                   | Treated as an empty string.                                            |

And four `$target` choices that pick the **backend**:

| `$target`               | Backend                          | When to reach for it                                                                         |
|-------------------------|----------------------------------|----------------------------------------------------------------------------------------------|
| `'php://temp'`          | Memory, spills to disk past 2 MiB | **Default for factories.** Best general-purpose choice; cheap for small bodies, safe for large. |
| `'php://memory'`        | Memory only                      | When you want to forbid disk spill (e.g. secrets).                                            |
| `null`                  | Pure PHP string                  | Tiny bodies (canned error pages, reason phrases). Cheapest backend, no FD allocation.         |
| (anything else)         | Rejected (`InvalidArgumentException`) | The implementation refuses to open arbitrary URLs — pass `php://*` or `null`.          |

When `$body` is a resource, `$target` is ignored — the resource is wrapped as-is.

```php
// php://temp — the default Factory choice
new Stream('payload', 'php://temp');

// in-memory string backend, no FD
new Stream('payload', null);

// already-open resource (e.g. a file)
new Stream(fopen('/var/files/report.pdf', 'rb'));

// from another StreamInterface (contents copied)
new Stream($otherStream);
```

## Reading

```php
$stream->getSize();              // ?int — null when not knowable (pipes, sockets)
$stream->tell();                 // int — cursor position
$stream->eof();                  // bool
$stream->isReadable();           // bool
$stream->isWritable();           // bool
$stream->isSeekable();           // bool
$stream->read(4096);             // string — up to N bytes
$stream->getContents();          // string — from cursor to EOF
(string) $stream;                // string — full body (rewinds first if seekable)
```

`getContents()` raises `RuntimeException` on a detached/closed stream. `__toString()` does **not** — see "The `__toString` contract" below.

## Writing

```php
$stream->write('data');          // int — bytes written
```

`write()` follows `fwrite()` semantics on every backend: bytes are written at the current cursor (overwriting in place when the cursor is below EOF, extending when at or past EOF), and the cursor advances by the number of bytes written.

```php
$stream = new Stream('hello world', null);
$stream->seek(6);
$stream->write('there');         // 5
(string) $stream;                 // "hello there"
$stream->tell();                  // 11
```

> **v2 → v3 note:** The legacy in-memory string backend (`target = null`) had two defects: when the cursor was at position 0 it *prepended* the payload instead of overwriting, and `tell()` always reported zero because the cursor was never advanced. Both are fixed in v3. See [Migration Guide](Migration-Guide.md).

## Seeking

```php
$stream->rewind();
$stream->seek(0, SEEK_SET);
$stream->seek(-10, SEEK_END);
```

Seek raises `RuntimeException` on detached / non-seekable streams. The in-memory string backend supports `SEEK_SET`, `SEEK_CUR`, `SEEK_END` and clamps out-of-bound offsets to the buffer length.

## Detach / close

```php
$resource = $stream->detach();   // returns the underlying resource (or null)
$stream->close();                // closes and forgets
```

For resource-backed streams, `detach()` simply unsets the wrapper's internal reference and returns the resource. For the in-memory string backend, `detach()` *materialises* the bytes into a fresh `php://memory` handle and seeks it to the same offset the in-memory cursor was sitting on:

```php
$stream = new Stream('abcdef', null);
$stream->seek(3);
$resource = $stream->detach();
ftell($resource);                 // 3
stream_get_contents($resource);    // "def"
```

After `detach()` / `close()`, calls on the wrapper that need the stream throw `RuntimeException` — except `__toString()`, which returns `''`.

## The `__toString` contract

PSR-7 mandates: **`__toString` MUST NOT raise an exception.** This package honours that hard requirement — every failure path returns the empty string:

```php
$stream = new Stream('payload', 'php://temp');
$stream->close();
echo (string) $stream;            // "" — no exception, no warning
```

If you want a hard error on a broken stream, call `getContents()` directly — it still throws `RuntimeException`.

## `isEmpty()` and `isNotEmpty()`

Two convenience predicates on top of the PSR-7 surface:

```php
$stream->isEmpty();     // true iff size is known AND < 1
$stream->isNotEmpty();  // true iff size is known AND > 0
```

**Both return `false` for indeterminate streams** (pipes, sockets, chunked HTTP responses with no `Content-Length`). The package will not lie about a stream whose size it cannot prove — callers must handle "I don't know" explicitly.

> **v2 → v3 note:** v2's `isEmpty()` used `getSize() < 1`, which in PHP evaluates `null < 1` to `true` and mis-classified every unknown-size stream as empty. The v3 behaviour is the strict "known-empty" check above.

## Metadata

```php
$stream->getMetadata();          // array — same shape as stream_get_meta_data()
$stream->getMetadata('uri');     // mixed — single key
```

For the in-memory string backend, `getMetadata()` returns a minimal map (`uri => null`, `seekable => false`, `eof => bool`) — there's no real stream handle to interrogate.

## Cloning preserves independence

When a PSR-7 message is cloned (via any `with*()` mutator), the body is cloned too:

- **Resource backend** — contents are read and copied into a fresh `php://temp` handle. The new wrapper is fully writable; the original is untouched.
- **String backend** — PHP's copy-on-write does the work. The two wrappers share the same string value until one of them writes.

The two streams are independent after the clone; writing to one does not mutate the other. See [Overview & Immutability](PSR-7-Overview.md) for the broader contract.

## See also

- [Recipe — Streaming Large Files](Recipe-Streaming-Large-Files.md) for serving and consuming multi-MB payloads without loading them into RAM.
- [UploadedFile](Uploaded-File.md) — uses `Stream` internally for the `moveTo()` chunked copy path.
