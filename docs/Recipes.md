# Recipes

Task-oriented walk-throughs. Each one is self-contained — copy, paste, adapt.

| Recipe                                                  | What it covers                                                                 |
|---------------------------------------------------------|--------------------------------------------------------------------------------|
| [JSON Response](Recipe-JSON-Response.md)                   | Convenience producer, manual variants, RFC 9457 Problem Details, streaming JSON. |
| [Redirect](Recipe-Redirect.md)                             | 301 / 302 / 303 / 307, delayed redirect, sticky session cookies.                |
| [File Upload](Recipe-File-Upload.md)                       | Receiving uploads (single, simple array, nested), sending multipart bodies.     |
| [Streaming Large Files](Recipe-Streaming-Large-Files.md)   | Multi-MB downloads/uploads without loading into memory; SSE caveat.             |
| [Proxying Requests](Recipe-Proxying-Requests.md)           | Incoming `ServerRequest` → outgoing PSR-18 `Request` → emit response back.      |

## What's NOT a recipe

These are deliberately *not* covered here — they're the domain of higher-level libraries:

- **PSR-15 middleware pipelines.** Use `relay/relay`, `laminas/laminas-stratigility`, or your framework's built-in pipeline.
- **Authentication / authorisation.** Up to your auth library; this package gives you the request/response value objects to act on.
- **Routing.** Use `nikic/fast-route`, `league/route`, or your framework's router.
- **CSRF / CORS.** Middleware concerns; many PSR-15 middlewares exist.
- **Caching of upstream responses.** Wrap the `Client` in your own decorator, or use `kevinrob/guzzle-cache-middleware`-style adapters that target your chosen PSR-18 client.
- **Multipart encoding.** PSR-7 has no opinion on multipart encoding; use `guzzlehttp/psr7`'s `MultipartStream`, `symfony/mime`, or build the body yourself — see the [File Upload](Recipe-File-Upload.md) recipe for an example.

## Want one we don't have?

Open an issue at [github.com/InitPHP/HTTP/issues](https://github.com/InitPHP/HTTP/issues) describing the scenario; recipe additions are reviewed eagerly.
