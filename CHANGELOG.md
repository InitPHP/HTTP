# Changelog

All notable changes to **initphp/http** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.0] - Unreleased

### Added

- **PSR-7 v2 support.** `psr/http-message` constraint widened to `^1.0 || ^2.0`.
- **Stateless `ServerRequest::createFromGlobals()`** that accepts optional `$server/$get/$post/$cookies/$files` arrays and never caches its result. Safe under Swoole, RoadRunner, Octane, FrankenPHP.
- **Content-Type-aware body parsing** in `createFromGlobals` (JSON / urlencoded / multipart).
- **nginx + php-fpm header fallback** in `createFromGlobals` for environments without `apache_request_headers()`.
- **`Client::withTimeout()` / `withConnectTimeout()` / `withFollowRedirects()` / `withCurlOptions()`** for production-grade configuration.
- **`Facadable` trait and `FacadableInterface`** — canonical names replacing the misspelled `Facadeble[Interface]` (old names kept as `@deprecated` aliases).
- **`docs/` directory** with PSR-7/17/18 guides, emitter walk-throughs, recipes, status-code reference and an upgrade guide.
- **Comprehensive `tests/Unit/` suite** complementing the upstream PSR integration tests.
- **GitHub Actions CI** matrix covering PHP 7.4 – 8.4 (PHPUnit + PHPStan).
- **PHPStan level 5** static analysis with a baseline of intentional ignores.
- Packagist metadata enrichment (description, keywords, homepage, support links).
- Composer scripts: `composer test`, `composer test:coverage`, `composer phpstan`, `composer ci`.
- EditorConfig, CHANGELOG, contributor-friendly docblocks across `src/`.

### Changed (breaking)

- **`Request::createFromGlobals()` removed.** Use `ServerRequest::createFromGlobals()`.
- **`Request::_parameters` bag removed.** All of `__get/__set/__isset/all/get/has/merge/sendRequest` are gone. Use `getParsedBody()` / attributes / explicit `(new Client())->sendRequest($request)`.
- **Custom `InitPHP\HTTP\Message\Interfaces\*` interfaces removed.** Type-hint against `Psr\Http\Message\*Interface` directly. The old interfaces forced mutator contracts that conflicted with PSR-7 immutability.
- **`Client::sendRequest()`** no longer special-cases the concrete `Request` class or silently JSON-encodes a `_parameters` bag.
- **`Client::prepareRequest()`** (used by the verb helpers and `fetch()`) no longer accepts `array`, `DOMDocument`, `SimpleXMLElement`, or "any object". Body must be `string | resource | StreamInterface | null`. The `send_request()` helper still encodes arrays/`toArray()` objects as JSON.
- **`Client` default timeout** changed from 0 (no timeout) to 30 s; default connect timeout 10 s. Pass `->withTimeout(0)` to keep the legacy infinite wait.
- **`Response::redirect()`** always sets `Location`; `Refresh` is added on top when a non-zero delay is requested.
- **`Response::json()`** uses `JSON_THROW_ON_ERROR`; unencodable payloads now raise `InvalidArgumentException` instead of silently producing `false`. Content-Type is `application/json; charset=utf-8`.
- **`UploadedFile::__construct()` `$size` is now `?int`**, matching the PSR-7 nullable contract.
- **`UploadedFile::getSize(): ?int`**, matching the PSR-7 contract.
- **`Stream::__toString()` no longer throws** under any circumstance, per PSR-7's hard requirement.
- **`Stream::write()` string backend** now obeys `fwrite()` semantics (overwrite from cursor, advance position). The legacy prepend-at-position-0 behaviour is gone.
- **`Stream::str2resorce()` renamed** to `Stream::stringToResource()` (private). Materialised detach handle now preserves cursor position.
- **`RequestTrait::updateHostFormUri()` renamed** to `updateHostFromUri()`.
- **HTTP version whitelist widened** to accept `2`, `2.0`, `3`, `3.0` (alongside `1.0` and `1.1`).

### Fixed

- 500 reason phrase corrected from `'Internal ServerRequest Error'` to `'Internal Server Error'`.
- `Stream::isEmpty()` / `isNotEmpty()` return `false` for streams whose size is null (pipes, sockets), instead of mis-classifying them as empty.
- `Emitter` reason-phrase status-line check uses `!== ''` so a literal `'0'` reason is preserved.
- `Emitter` raises `EmitHeaderException` (not `EmitBodyException`) when `headers_sent()` is true.
- `Client` sets `CURLOPT_POSTFIELDS` for body-bearing methods (POST/PUT/PATCH/DELETE) even when the body is empty, so cURL doesn't silently downgrade the request.
- `Client::sendRequest()` wraps the response body in `php://temp` instead of an in-memory PHP string, avoiding OOM on multi-megabyte downloads.
- `UploadedFile::moveTo()` survives partial-write iterations and retries until the chunk is flushed.
- `ServerRequest::normalizeFiles()` recurses into arbitrarily nested file input names (`file[parent][child][…]`).
- `send_request()` (global helper) requires a URL when the first arg is a method string instead of silently sending to `null`.

### Removed (in addition to breaking changes above)

- `Request::__set/__get/__isset/all/get/has/merge/sendRequest/createFromGlobals` and the static singleton property that backed `createFromGlobals`.
- `Client::sendRequest`'s `instanceof Request` branch.
- `Client::prepareRequest`'s `DOMDocument`/`SimpleXMLElement`/`toArray()`/`get_object_vars()` cascade.
- `src/Message/Interfaces/` directory (six custom interfaces).

### Deprecated

- `InitPHP\HTTP\Facade\Interfaces\FacadebleInterface` (use `FacadableInterface`).
- `InitPHP\HTTP\Facade\Traits\Facadeble` (use `Facadable`).

Both will be removed in 4.0.

## [2.x] - prior releases

See Git history for entries prior to this changelog.
