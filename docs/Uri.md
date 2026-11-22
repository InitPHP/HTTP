# Uri

`InitPHP\HTTP\Message\Uri` is the package's PSR-7 `UriInterface` value-object, parsed at construction time.

```php
use InitPHP\HTTP\Message\Uri;
```

## Construction

```php
$uri = new Uri('https://alice:secret@api.example.com:8443/v1/users?active=true#contact');
```

An unparseable string raises `InvalidArgumentException`. An empty string is valid — it produces a `Uri` with every component empty / null.

```php
$uri = new Uri('');
(string) $uri;             // ""
```

## Reading

```php
$uri->getScheme();         // "https"
$uri->getUserInfo();       // "alice:secret"
$uri->getHost();           // "api.example.com"
$uri->getPort();           // 8443 — see "Standard ports collapse" below
$uri->getAuthority();      // "alice:secret@api.example.com:8443"
$uri->getPath();           // "/v1/users"
$uri->getQuery();          // "active=true"
$uri->getFragment();       // "contact"
(string) $uri;             // round-trips the whole URI
```

## Immutable mutators

```php
$uri = (new Uri('https://example.com/'))
    ->withScheme('http')
    ->withHost('api.example.com')
    ->withPort(8080)
    ->withPath('/v2/users')
    ->withQuery('limit=50&offset=0')
    ->withFragment('top')
    ->withUserInfo('alice', 'secret');
```

Each `with*()` returns a new `Uri`; the original is untouched.

`withPort()` accepts `null` to clear an explicitly-set port; a port outside `0..65535` raises `InvalidArgumentException`.

## Standard ports collapse

`getPort()` returns `null` when the port is the default for the scheme. This is the PSR-7 rule — clients deciding whether to emit an explicit port in the `Host` header don't have to special-case standard ports themselves.

```php
(new Uri('https://example.com:443'))->getPort();   // null
(new Uri('http://example.com:80'))->getPort();     // null
(new Uri('http://example.com:8080'))->getPort();   // 8080
```

Reflected in the string round-trip and the authority:

```php
(string) (new Uri('https://example.com:443/x'));   // "https://example.com/x"
(string) (new Uri('http://example.com:8080/x'));   // "http://example.com:8080/x"
```

## Percent-encoding

The constructor and the `with*()` family run their inputs through per-component percent-encoding filters that escape any byte outside the RFC 3986 character class for that component. Already-percent-encoded sequences are preserved verbatim:

```php
$uri = new Uri('https://example.com/cafe%CC%81/menu');
$uri->getPath();                     // "/cafe%CC%81/menu"   — kept as-is

$uri = (new Uri('https://example.com'))->withPath('/c a f é');
$uri->getPath();                     // "/c%20a%20f%20%C3%A9"
```

Each component (path, query+fragment, userinfo) uses a slightly different allowed-character set — see the regexes in `Uri::filterPath()`, `Uri::filterQueryAndFragment()` and `Uri::filterUserInfoComponent()` for the specifics.

## `set*()` vs `with*()`

`Uri` also exposes in-place `setScheme/setHost/setPort/setPath/setQuery/setFragment/setUserInfo` mutators that modify the instance and return `$this`. They're useful inside builders (a single chain that constructs and immediately uses a URI) but should never escape — once a URI has been handed to a message, treat it as frozen and use `with*()`. The PSR-7 contract that this package implements only includes the `with*()` family. See [Overview & Immutability](PSR-7-Overview.md).

## Building URIs from parts

```php
$uri = (new Uri())
    ->withScheme('https')
    ->withHost('api.example.com')
    ->withPath('/v1/users')
    ->withQuery(http_build_query(['active' => 'true']));

(string) $uri;    // "https://api.example.com/v1/users?active=true"
```

Or with the static factory facade:

```php
use InitPHP\HTTP\Facade\Factory;
$uri = Factory::createUri('https://api.example.com/');
```

## See also

- [Request](Request.md) — `withUri()` and the Host-header synchronisation rule.
- [PSR-17 Factory](PSR-17-Factory.md) — `createUri()` for DI-friendly construction.
