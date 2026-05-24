# Uri

`InitPHP\HTTP\Message\Uri` is a PSR-7 `UriInterface` value-object parsed at construction time:

```php
use InitPHP\HTTP\Message\Uri;

$uri = new Uri('https://alice:secret@api.example.com:8443/v1/users?active=true#contact');

$uri->getScheme();    // "https"
$uri->getUserInfo();  // "alice:secret"
$uri->getHost();      // "api.example.com"
$uri->getPort();      // 8443
$uri->getAuthority(); // "alice:secret@api.example.com:8443"
$uri->getPath();      // "/v1/users"
$uri->getQuery();     // "active=true"
$uri->getFragment();  // "contact"
(string) $uri;        // round-trips the whole URI
```

A malformed input string raises `InvalidArgumentException`.

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

## Standard ports collapse

`getPort()` returns `null` when the port is the default for the scheme:

```php
(new Uri('https://example.com:443'))->getPort();   // null
(new Uri('http://example.com:80'))->getPort();     // null
(new Uri('http://example.com:8080'))->getPort();   // 8080
```

This matches PSR-7 — clients deciding whether to emit an explicit port in the `Host` header don't have to special-case standard ports themselves.

## Path encoding

The constructor (and `withPath()` / `withQuery()` / `withFragment()`) run the input through a percent-encoding filter that escapes any byte outside the RFC 3986 character classes for that component. Already-percent-encoded sequences are preserved:

```php
$uri = new Uri('https://example.com/cafe%CC%81/menu');
$uri->getPath();      // "/cafe%CC%81/menu"   — kept as-is

$uri = (new Uri('https://example.com'))->withPath('/c a f é');
$uri->getPath();      // "/c%20a%20f%20%C3%A9"
```

Userinfo, query, and fragment components are encoded against slightly different allowed-character classes — see the source of `Uri::filterPath()`, `filterQueryAndFragment()`, and `filterUserInfoComponent()` for the regex specifics.

## Set vs With

The concrete `Uri` also exposes `setScheme/setHost/setPort/setPath/setQuery/setFragment/setUserInfo` mutators that modify the instance in place. These are useful inside builders (a single chain that constructs and immediately uses a URI) but should never escape into shared state — once a URI has been handed to a message, treat it as frozen and use `with*()`. The PSR-7 contract that this package implements only includes the `with*()` family.
