# Migration Guide — 2.x → 3.x

Version 3.0 cleans up several long-standing design defects. Most upgrades are mechanical search-and-replace; the trickiest ones are the `_parameters` bag removal and the `Request::createFromGlobals()` move.

## Quick checklist

- [ ] Replace `InitPHP\HTTP\Message\Interfaces\*` with `Psr\Http\Message\*`.
- [ ] Replace `Request::createFromGlobals()` with `ServerRequest::createFromGlobals()`.
- [ ] Replace any `$request->name = $value` / `$request->all()` / `$request->get('name')` with the PSR-7 attribute or parsed-body API.
- [ ] Replace `$request->sendRequest()` with explicit `(new Client())->sendRequest($request)`.
- [ ] Pre-encode array bodies before passing them to `Client::sendRequest()` (the helper `send_request()` still auto-encodes).
- [ ] If you depended on the legacy "no timeout" behaviour, call `->withTimeout(0)` explicitly.
- [ ] If you imported the misspelled `Facadeble` / `FacadebleInterface`, switch to `Facadable` / `FacadableInterface` (old names still resolve but are `@deprecated`).

---

## Breaking changes in detail

### 1. Custom message interfaces removed

The `InitPHP\HTTP\Message\Interfaces\*` mirror set (`MessageInterface`, `RequestInterface`, `ResponseInterface`, `ServerRequestInterface`, `StreamInterface`, `UriInterface`) is gone. Each had mandatory `setX` / `outX` mutators that conflicted with PSR-7's immutability prose; any third-party PSR-7 type fed into a function type-hinted against them was rejected at compile time.

```diff
- use InitPHP\HTTP\Message\Interfaces\RequestInterface;
+ use Psr\Http\Message\RequestInterface;
```

The concrete classes still implement everything PSR-7 requires; only the package-local interfaces are gone.

### 2. `Request::createFromGlobals()` removed

Moved to `ServerRequest` (its conceptually correct home), and now stateless:

```diff
- $request = InitPHP\HTTP\Message\Request::createFromGlobals();
+ $request = InitPHP\HTTP\Message\ServerRequest::createFromGlobals();
```

The new factory takes optional `$server, $get, $post, $cookies, $files` arguments — pass them explicitly under long-running PHP runtimes (Swoole, RoadRunner, Octane, FrankenPHP) and in tests so you don't depend on superglobal state.

### 3. The `_parameters` parameter bag is gone

`__get` / `__set` / `__isset` / `all()` / `get()` / `has()` / `merge()` no longer exist on `Request`. Use the PSR-7 alternatives:

```diff
- $request->name;
- $request->get('name');
- $request->has('name');
- $request->all();
- $request->merge($_GET, $_POST, $rawData);
+ $parsed = $request->getParsedBody() ?? [];
+ $name   = $parsed['name'] ?? null;
+
+ // For middleware-set state:
+ $request = $request->withAttribute('name', $value);
+ $name    = $request->getAttribute('name');
```

`ServerRequest::createFromGlobals()` populates `parsedBody` automatically when the request advertises a known `Content-Type` — JSON, urlencoded, multipart — so the typical case "read the field the client submitted" works out of the box.

### 4. `Request::sendRequest()` removed

The convenience hook that built and dispatched a client inline is gone — it hard-coded a fresh `Client` per call and made DI / testing painful:

```diff
- $response = $request->sendRequest();
+ $response = (new InitPHP\HTTP\Client\Client())->sendRequest($request);
```

Or via the facade:

```diff
+ $response = InitPHP\HTTP\Facade\Client::sendRequest($request);
```

### 5. `Client::sendRequest()` no longer encodes array bodies

The previous client sniffed `$request instanceof Request` and silently turned the `_parameters` bag into a JSON body. That violated PSR-18's "send what you got" contract. v3 only accepts `string | resource | StreamInterface | null` bodies.

```diff
- // Old: $request had ->name = 'Ada' and the client encoded it for us.
- (new Client())->sendRequest($request);
+ $request = $request
+     ->withBody(new Stream(json_encode($payload), null))
+     ->withHeader('Content-Type', 'application/json');
+ (new Client())->sendRequest($request);
```

The `send_request()` global helper still handles convenience JSON-encoding for arrays — see [Helpers](Helpers.md).

### 6. `Client::prepareRequest()` no longer accepts DOM / SimpleXML / arrays

`Client::fetch() / get() / post() / put() / patch() / delete() / head()` previously coerced `DOMDocument`, `SimpleXMLElement`, `array`, and "any object with `__toString()` or `toArray()`" into a body. That responsibility belongs in the application — different APIs need different serialisation choices. v3 accepts only `string | resource | StreamInterface | null`.

If you need the old behaviour for arrays specifically, the `send_request()` helper still does it; for DOM / SimpleXML, encode before passing in:

```diff
- $client->post($url, $dom);
+ $client->post($url, $dom->saveHTML());

- $client->post($url, $simpleXml);
+ $client->post($url, $simpleXml->asXML());
```

### 7. `Client` has new timeouts

Defaults: 30 s request timeout (`CURLOPT_TIMEOUT`), 10 s connect timeout (`CURLOPT_CONNECTTIMEOUT`). The old default was 0 (no timeout). If you specifically rely on infinite waits (long-poll, SSE):

```php
$client = (new Client())->withTimeout(0)->withConnectTimeout(10);
```

### 8. `UploadedFile` size is now nullable

`UploadedFile::__construct(?int $size, ...)` and `UploadedFile::getSize(): ?int` now match the PSR-7 contract. Code that did `int $size = $file->getSize()` will need a null check on streams whose size isn't reportable.

### 9. Facade naming corrected

```diff
- use InitPHP\HTTP\Facade\Traits\Facadeble;
- use InitPHP\HTTP\Facade\Interfaces\FacadebleInterface;
+ use InitPHP\HTTP\Facade\Traits\Facadable;
+ use InitPHP\HTTP\Facade\Interfaces\FacadableInterface;
```

The old names remain as `@deprecated` aliases and continue to work, but will be removed in a future major release.

### 10. `Response::redirect()` always sets `Location`

Previously, when `$second > 0`, only the `Refresh` header was set — crawlers and HTTP libraries that ignore the non-standard `Refresh` could not follow the redirect. v3 always sets `Location` and adds `Refresh` on top when a delay is requested.

No code changes needed if you used `redirect(..., $status, 0)`; if you set a non-zero delay and relied on the absence of `Location`, that absence is gone.

### 11. `Response::json()` is now strict

Uses `JSON_THROW_ON_ERROR` and translates failure into `InvalidArgumentException`. Code that used to silently produce `false` bodies on unencodable input will now throw.

The Content-Type also gained an explicit charset:

```diff
- Content-Type: application/json
+ Content-Type: application/json; charset=utf-8
```

### 12. `Internal Server Error` typo fix

The 500 reason phrase was previously `'Internal ServerRequest Error'`. v3 emits the canonical `'Internal Server Error'`. Log-aggregation rules that match on the literal old string will miss new responses.

### 13. `Stream::__toString()` never throws

PSR-7's hard requirement; the old implementation propagated `RuntimeException` from detached streams. v3 returns an empty string instead. If your code relied on the throw to detect a detached stream, switch to `$stream->getContents()` (still throws) or check via `isset/eof` first.

### 14. `Stream::write()` (string backend) obeys `fwrite()` semantics

The `target = null` (pure in-memory string) backend used to *prepend* at position 0 and never advance the cursor. v3 overwrites from the current position and advances `tell()` correctly. If you wrote code that relied on the broken prepend behaviour, swap to `new Stream($newPrefix . $oldBody, null)`.

### 15. `Stream::isEmpty()` / `isNotEmpty()` no longer lie about unknown size

The previous `getSize() < 1` test mis-classified pipes/sockets as empty. v3 returns `false` for both predicates when the size is `null` (indeterminate). Callers branching on "unknown" must check `getSize() === null` themselves.

### 16. `EmitHeaderException` is now actually raised

v2 always raised `EmitBodyException` for both "headers already sent" and "output buffer dirty" failure modes. v3 splits them — `EmitHeaderException` for the headers case, `EmitBodyException` for the body case. Both still extend `\RuntimeException`, so single-catch handlers keep working.

### 17. `Stream::str2resorce` renamed and bug-fixed

Private method renamed to `Stream::stringToResource` (private). Beyond the spelling fix, the materialised detach handle now preserves the cursor position instead of always returning a handle positioned at byte zero. Only affects code that explicitly called the private method via reflection (don't do that).

### 18. `RequestTrait::updateHostFormUri` renamed

Renamed to `updateHostFromUri`. The trait is internal to this package; only matters if you extended one of the trait consumers and overrode the method.

### 19. HTTP version whitelist widened

`Response::__construct()` now accepts `'2'`, `'3'`, `'3.0'` in addition to `'1.0'`, `'1.1'`, `'2.0'`. If you constructed with `'2'` previously and got an `InvalidArgumentException`, that's now valid.

### 20. Native return types added for PSR-7 v2 covariance

Seven methods gained explicit language-level return types to satisfy the tightened `psr/http-message: ^2.0` contract (v1 left them untyped at the interface level; v2 declares them):

| Method                                       | Added return type   |
|----------------------------------------------|---------------------|
| `Stream::close()`                            | `: void`            |
| `Stream::seek($offset, $whence = SEEK_SET)` | `: void`            |
| `Stream::rewind()`                           | `: void`            |
| `MessageTrait::getHeader($name)`             | `: array`           |
| `UploadedFile::getStream()`                  | `: StreamInterface` |
| `UploadedFile::moveTo($targetPath)`          | `: void`            |
| `Uri::__toString()`                          | `: string`          |

Existing PHPDoc `@return` lines already documented these types — only the runtime signature changed. **No call-site code needs to change.**

> These signatures now match the PSR-7 v2 interfaces: an implementation that omits a return type the interface declares is a fatal error at class-load time. The fix is uniform across all supported PHP versions.

**However, if you extended any of these classes and overrode one of those methods**, your override's signature must now match the new return type (or stay untyped — PHP allows that). If your override declared a *different* return type (e.g. `: bool`), it will now fail with a fatal at class load time. Update overrides accordingly.

---

## Things that are NOT breaking

- **PSR-7 / PSR-17 / PSR-18 spec behaviour** — the integration test suites still pass 100%.
- **All `with*()` mutators on every message type** — names, signatures, and immutability behaviour are unchanged.
- **The static facades** — `InitPHP\HTTP\Facade\{Client, Emitter, Factory}` still resolve to the same singleton with the same surface.
- **`send_request()` global helper** — same signature, accepts arrays / objects with `toArray()` / `__toString()` exactly as before.

If something on this list broke for you, please file an issue at [github.com/InitPHP/HTTP/issues](https://github.com/InitPHP/HTTP/issues).
