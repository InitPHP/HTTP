# ServerRequest

`InitPHP\HTTP\Message\ServerRequest` is the PSR-7 server-side request: the one your application receives. On top of `Request` it carries the server params, cookies, query string, parsed body, uploaded files and a free-form attribute bag.

It implements `Psr\Http\Message\ServerRequestInterface`.

```php
use InitPHP\HTTP\Message\ServerRequest;
```

## Hydrating from globals (the typical use case)

```php
$request = ServerRequest::createFromGlobals();
```

One line replaces the ten-line `new ServerRequest(...)` recipe. The factory:

1. Reads `$_SERVER`, `$_GET`, `$_POST`, `$_COOKIE`, `$_FILES` as defaults.
2. Computes the URI from `HTTPS` / `HTTP_HOST` / `SERVER_NAME` / `SERVER_PORT` / `REQUEST_URI`.
3. Collects headers via `apache_request_headers()` when available, **or** falls back to walking `$_SERVER` for `HTTP_*` and `CONTENT_*` keys (so nginx + php-fpm and FrankenPHP work).
4. Stages the request body on `php://temp` (large bodies spill to disk, not memory).
5. Parses the body according to `Content-Type` and stores the result in `parsedBody`:
   - `application/json` → `json_decode(..., true)` (if the decoded value is an array; otherwise `null`).
   - `application/x-www-form-urlencoded` → `$_POST` if populated, else `parse_str(...)`.
   - `multipart/form-data` → `$_POST` (already populated by PHP).
   - Anything else → `parsedBody` left as `null`.
6. Normalises `$_FILES` into a tree of `UploadedFileInterface` instances via `normalizeFiles()`, including arbitrarily nested input names (`file[parent][child][…]`).

### Stateless

**Every call returns a fresh instance.** There is no static caching. This is what makes the factory safe under long-running PHP runtimes — Swoole, RoadRunner, Octane, FrankenPHP — where a single process serves many requests:

```php
$a = ServerRequest::createFromGlobals(
    ['REQUEST_METHOD' => 'GET',  'REQUEST_URI' => '/one', 'HTTP_HOST' => 'a.test'],
    [], [], [], []
);
$b = ServerRequest::createFromGlobals(
    ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/two', 'HTTP_HOST' => 'b.test'],
    [], [], [], []
);

$a === $b;             // false — fresh instances
$a->getMethod();       // "GET"
$b->getMethod();       // "POST"
```

> **v2 → v3 note:** The legacy `Request::createFromGlobals()` cached its result in a static property and reused it forever. That broke under any non-FPM runtime. The new factory is on `ServerRequest`, not `Request`, and is stateless. See [Migration Guide](Migration-Guide.md).

### Explicit arguments (for tests and exotic runtimes)

Pass arrays explicitly to drive the factory without touching the real superglobals:

```php
$request = ServerRequest::createFromGlobals(
    ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/cart', 'HTTP_HOST' => 'shop.test',
     'CONTENT_TYPE'   => 'application/json'],
    [],                                 // $_GET
    [],                                 // $_POST (unused for JSON)
    [],                                 // $_COOKIE
    []                                  // $_FILES
);
```

Test note: the JSON branch reads `php://input` for the body — under PHPUnit that's empty, so `parsedBody` will be `null` for JSON unless you provide the body via another route. Use `$request->withParsedBody([...])` in tests.

## Constructing without the factory

```php
$request = new ServerRequest(
    'POST',                                  // method
    '/checkout',                             // URI
    ['Content-Type' => 'application/json'],  // headers
    '{"sku":"X-1"}',                         // body
    '1.1',                                   // version
    $_SERVER                                 // server params
);

$request = $request
    ->withCookieParams($_COOKIE)
    ->withQueryParams($_GET)
    ->withParsedBody(['sku' => 'X-1'])
    ->withUploadedFiles($request->normalizeFiles($_FILES))
    ->withAttribute('route', '/checkout');
```

## Reading

`ServerRequest` exposes every PSR-7 `Request` accessor (see [Request](Request.md)) plus:

```php
$request->getServerParams();     // array — the snapshot from $_SERVER
$request->getCookieParams();     // array — the snapshot from $_COOKIE
$request->getQueryParams();      // array — the snapshot from $_GET
$request->getParsedBody();       // null|array|object — Content-Type-driven
$request->getUploadedFiles();    // array<string, UploadedFileInterface|array>
$request->getAttributes();       // array — middleware-set state
$request->getAttribute('name');  // value or null
$request->getAttribute('name', $default);
```

## Mutating (returns a new instance)

```php
$updated = $request
    ->withCookieParams(['session' => 'abc'])
    ->withQueryParams(['q' => 'phpunit'])
    ->withParsedBody(['sku' => 'X-1'])
    ->withUploadedFiles($files)
    ->withAttribute('user', $user)
    ->withoutAttribute('temp');
```

`withParsedBody()` accepts `array`, `object`, or `null`. Anything else raises `InvalidArgumentException`.

## Uploaded files

The `normalizeFiles()` instance method converts PHP's `$_FILES` shape (or a pre-built tree of `UploadedFileInterface`s) into a normalised tree. It supports:

- The single-file shape: `['avatar' => ['tmp_name' => ..., 'size' => ...]]`.
- The simple-array shape (`<input type="file" name="docs[]" multiple>`).
- Arbitrarily nested names (`<input name="docs[brief]">`, `<input name="docs[exhibits][a]">`, ...).
- Pre-built `UploadedFileInterface` instances mixed in anywhere in the tree.

See [UploadedFile](Uploaded-File.md) for the moving / streaming / error handling surface.

## Attributes — the middleware scratch pad

Attributes are the place middleware stash decoded JWT claims, matched route parameters, DI handles, audit IDs:

```php
$request = $request->withAttribute('user', $authenticatedUser);

$user = $request->getAttribute('user');           // value or null
$user = $request->getAttribute('user', $guest);   // value or fallback
$request = $request->withoutAttribute('user');
```

`getAttributes()` returns the entire bag as an associative array.

## Convenience method predicates

All the predicates from `RequestTrait` (`isGet`, `isPost`, `isMethod(...)`, ...) are available; see [Request](Request.md).

## See also

- [Recipe — File Upload](Recipe-File-Upload.md) for the receiving / moving / nested-name flow.
- [Recipe — Proxying Requests](Recipe-Proxying-Requests.md) for forwarding an incoming `ServerRequest` to an upstream service.
