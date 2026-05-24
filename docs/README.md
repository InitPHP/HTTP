# InitPHP HTTP — Documentation

This directory contains the long-form documentation. The top-level [`README.md`](../README.md) covers installation and a tour; the pages here go deeper.

## Reading order

1. [Getting Started](getting-started.md) — install, run the test suite, the smallest possible example.
2. PSR-7 — building blocks
   - [Messages (Request / Response)](psr7/messages.md)
   - [ServerRequest](psr7/server-request.md)
   - [Streams](psr7/streams.md)
   - [Uri](psr7/uri.md)
   - [Uploaded Files](psr7/uploaded-files.md)
3. PSR-17 — factories
   - [The unified Factory](psr17/factory.md)
4. PSR-18 — clients
   - [Client basics](psr18/client.md)
   - [Configuration (timeouts, redirects, raw cURL options)](psr18/configuration.md)
   - [Exceptions and PSR-18 error contract](psr18/exceptions.md)
5. Emitter — turning a Response into bytes
   - [Basic emission](emitter/basic-emission.md)
   - [Chunked bodies](emitter/chunked-bodies.md)
   - [Content-Range / partial content](emitter/content-range.md)
6. Static facades
   - [Overview and when to use them](facades/overview.md)
   - [Replacing the singleton with a custom instance](facades/customization.md)
7. Recipes (task-oriented)
   - [JSON responses](recipes/json-response.md)
   - [Redirects](recipes/redirect.md)
   - [File uploads](recipes/file-upload.md)
   - [Streaming large files](recipes/streaming-large-files.md)
   - [Proxying requests](recipes/proxying-requests.md)
8. Reference
   - [HTTP status code phrases](reference/http-status-codes.md)
9. [Upgrade guide (2.x → 3.x)](upgrade-guide.md)

## A note on the examples

Every code block in these pages was written against PHP 7.4 and is type-safe under PHPStan level 5. Newer PHP versions are obviously supported but no example uses 8.x-only syntax (no constructor promotion, no `mixed`, no enums) so you can drop snippets into a 7.4 codebase verbatim.
