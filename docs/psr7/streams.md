# Streams

PSR-7 message bodies are `Psr\Http\Message\StreamInterface`. The implementation in this package, `InitPHP\HTTP\Message\Stream`, supports three backends selected by the constructor's second argument:

```php
use InitPHP\HTTP\Message\Stream;

new Stream($contents, 'php://temp');   // default — disk-spilling temp file
new Stream($contents, 'php://memory'); // in-memory PHP stream
new Stream($contents, null);           // pure in-memory string buffer
new Stream($resource);                 // wraps an existing resource handle
new Stream($otherStream);              // copies an existing StreamInterface
```

## Choosing a backend

| Backend         | When to use                                                                                    |
|-----------------|-----------------------------------------------------------------------------------------------|
| `php://temp`    | **Default**. Small payloads stay in memory, larger ones spill to disk (default 2 MiB).         |
| `php://memory`  | When you know the payload is small AND you want to forbid disk spill (e.g. secrets).           |
| `null` (string) | Tiny constants like reason phrases or canned error bodies; cheapest backend, no FD allocation. |
| resource        | When you already opened the handle yourself (e.g. file upload, `fopen('php://input', 'r')`).   |

The `null` backend is a recent addition for the cases where a real stream feels too heavy — it implements the full `StreamInterface` contract by keeping the bytes in a regular PHP string.

## Writing

`write($string)` follows `fwrite()` semantics:

```php
$stream = new Stream('hello', null);
$stream->write(' world');         // appends past EOF
(string) $stream;                  // "hello world"
echo $stream->tell();             // 11

$stream = new Stream('hello world', null);
$stream->seek(6);
$stream->write('there');          // overwrites from position 6
(string) $stream;                  // "hello there"
echo $stream->tell();             // 11
```

The string backend uses substring overwrite-or-extend exactly like a real seekable stream; the position is advanced by the number of bytes actually written.

## Reading

```php
$stream->rewind();
$first = $stream->read(1024);
$rest  = $stream->getContents();
```

`(string) $stream` performs a `rewind()` (if seekable) and returns the full contents. **It never throws.** A detached or otherwise unreadable stream returns an empty string from `__toString` — this is a PSR-7 hard requirement.

```php
$stream = new Stream('x', 'php://temp');
$stream->close();
echo (string) $stream; // "" — quiet failure, not an exception
```

If you need a hard error, use `getContents()` directly; it raises `RuntimeException` on a detached stream.

## isEmpty / isNotEmpty

```php
$stream->isEmpty();      // true when size is known and < 1
$stream->isNotEmpty();   // true when size is known and > 0
```

Both return `false` for **indeterminate** streams (pipes, sockets, chunked responses with no `Content-Length`). Callers branching on these must handle the "I don't know" case explicitly — there's deliberately no boolean that lies.

## detach()

`detach()` releases the underlying resource and returns it to the caller. For resource-backed streams it's just `unset($this->stream); return $resource`. For the `null` (string) backend the bytes are materialised into a `php://memory` handle whose cursor is positioned at the same offset the string backend was sitting on — so callers who detached a partially-read string-backed stream get the exact same view as a resource.

```php
$stream = new Stream('abcdef', null);
$stream->seek(3);
$resource = $stream->detach();    // resource handle
ftell($resource);                  // 3
```

## Cloning preserves independence

When a message is cloned (`withHeader`, `withStatus`, ...) the body is cloned too. The clone gets its own buffer; writing to it does **not** mutate the original:

```php
$request = new Request('GET', '/', [], new Stream('original', 'php://temp'));
$clone   = $request->withHeader('X-Test', 'yes');

$clone->getBody()->write(' tampered');

(string) $request->getBody(); // "original"  — untouched
(string) $clone->getBody();   // " tamperedl" or similar — clone has its own
```

For resource-backed streams the contents are copied into a new `php://temp` handle; for string-backed streams PHP's copy-on-write does the work. Either way, the two messages are independent.
