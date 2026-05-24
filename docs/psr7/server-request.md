# ServerRequest

`ServerRequest` extends the basic [Request](messages.md) with the slots a framework needs to receive an incoming HTTP request: server params, cookies, query string, parsed body, uploaded files, and a free-form attribute bag.

## Constructing

```php
use InitPHP\HTTP\Message\ServerRequest;

$request = new ServerRequest(
    'POST',                       // method
    '/checkout',                  // URI
    ['Content-Type' => 'application/json'],
    '{"sku":"X-1"}',              // body
    '1.1',                        // version
    $_SERVER                      // server params
);

$request = $request
    ->withCookieParams($_COOKIE)
    ->withQueryParams($_GET)
    ->withParsedBody(['sku' => 'X-1'])
    ->withUploadedFiles($request->normalizeFiles($_FILES));
```

Everything except the constructor pair (`$method`, `$uri`) is optional and can be set later via the PSR-7 `with*()` family.

## Hydrating from globals

For most applications you don't want to wire all of the above manually — call the dedicated factory instead:

```php
$request = ServerRequest::createFromGlobals();
```

That's a one-liner equivalent to the seven-line `new ServerRequest(...)` recipe above. It's also **stateless**: every call returns a freshly computed instance. This is the property that makes the package safe under Swoole, RoadRunner, Octane and FrankenPHP, where a single PHP process may serve many HTTP requests in sequence.

For tests, or when running under exotic runtimes that don't populate the superglobals, pass arrays explicitly:

```php
$request = ServerRequest::createFromGlobals(
    $server  = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/cart', 'HTTP_HOST' => 'shop.test'],
    $get     = [],
    $post    = ['sku' => 'X-1'],
    $cookies = [],
    $files   = []
);
```

### Content-Type-aware body parsing

`createFromGlobals` reads `php://input` and parses it according to the advertised `Content-Type`:

| Content-Type                          | Parsed body                            |
|---------------------------------------|----------------------------------------|
| `application/json`                    | `json_decode($body, true)`             |
| `application/x-www-form-urlencoded`   | `$_POST` if non-empty, else `parse_str` |
| `multipart/form-data`                 | `$_POST` (PHP already populated it)    |
| Anything else (including no header)   | `parsedBody` left as `null`            |

JSON payloads that fail to decode (or decode to a non-array value) leave `parsedBody` as `null` — they don't throw. Use `(string) $request->getBody()` if you need the raw bytes.

### Header collection on nginx + php-fpm

`apache_request_headers()` is only available under mod_php. On nginx + php-fpm (and FrankenPHP), `createFromGlobals` falls back to walking `$_SERVER` for `HTTP_*` and `CONTENT_*` keys and normalising them back into header case (`HTTP_X_TRACE_ID` → `X-Trace-Id`).

## Working with uploaded files

```php
foreach ($request->getUploadedFiles() as $field => $file) {
    if ($file instanceof \Psr\Http\Message\UploadedFileInterface) {
        $file->moveTo(__DIR__ . '/uploads/' . $file->getClientFilename());
    } elseif (is_array($file)) {
        // Nested input names: file[parent][child]
        foreach ($file as $child) { ... }
    }
}
```

`normalizeFiles()` handles arbitrarily nested input names (`file[parent][child][...]`) — see [Uploaded Files](uploaded-files.md) for the recursion details.

## Attributes

Attributes are the framework-side scratch pad — a place for middleware to stash decoded JWT claims, matched route parameters, dependency-injection handles, and the rest:

```php
$request = $request->withAttribute('user', $authenticatedUser);

$user = $request->getAttribute('user');        // value or null
$user = $request->getAttribute('user', $guest); // value or fallback
$request = $request->withoutAttribute('user');
```

`getAttributes()` returns the entire bag as an associative array.

## Why no singleton

The previous (`2.x`) implementation cached the first `createFromGlobals()` result in a static property and reused it forever. That worked under PHP-FPM because the process dies after one request, but it broke under any long-running runtime — the second HTTP request saw the first one's data.

The v3 implementation has no static cache. Each call computes a fresh instance from the supplied (or default) superglobal snapshots. If you want process-wide caching, do it at your application layer where you control the lifecycle.
