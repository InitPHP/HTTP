# Recipe — Redirect

## Permanent (301)

```php
use InitPHP\HTTP\Message\Response;

$response = (new Response())->redirect('https://example.com/new-home', 301);
```

Sets `Location: https://example.com/new-home` and status 301. Use 301 for SEO-relevant moves you want cached by search engines and browsers.

## Temporary (302)

```php
$response = (new Response())->redirect('/dashboard', 302);
```

302 (Found) is the workhorse — "after login, send the user back" flows, post-form rewrites, etc.

## See Other (303) — Post / Redirect / Get

After processing a POST, send 303 to force the browser to do a GET on the next URL — the classic anti-double-submit pattern:

```php
$response = (new Response())->redirect('/orders/' . $orderId, 303);
```

## Temporary Redirect (307) — preserve method

If you specifically need the client to **preserve the request method** (e.g. POST → POST), use 307 — 302's spec was historically loose and many clients silently rewrote POST to GET:

```php
$response = (new Response())->redirect('/checkout', 307);
```

For the permanent equivalent ("method-preserving 301") there's 308, supported the same way.

## With a delay — "thanks for shopping, redirecting in 5s"

```php
$response = (new Response())->redirect('https://example.com/thanks', 200, 5);
```

The delay variant:

- **Always** sets `Location` (regression-protected — see [Migration Guide](Migration-Guide.md)) so non-browser clients still follow the redirect.
- **Additionally** sets `Refresh: 5; url=https://example.com/thanks` so browsers honour the countdown.
- Status defaults to 302 if you don't pass one; the example above uses 200 so the page body actually renders during the wait.

## Sticky session cookie across the redirect

PSR-7 leaves cookie handling to your session middleware; preserve the session by setting `Set-Cookie` on the response before emitting:

```php
$response = (new Response())
    ->redirect('/dashboard', 302)
    ->withAddedHeader('Set-Cookie', sprintf(
        '%s=%s; Path=/; Secure; HttpOnly; SameSite=Lax',
        session_name(),
        session_id()
    ));
```

Keep `Secure` and `HttpOnly` on a session cookie: without `Secure` the id is also sent over plain HTTP, and without `HttpOnly` a script injected into the page can read it. (Serve the site over HTTPS; for plain-HTTP local development, drop `Secure` there only.)

## Validation

`redirect()` raises `InvalidArgumentException` if you pass anything that isn't a string or `Psr\Http\Message\UriInterface`:

```php
try {
    (new Response())->redirect(12345);
} catch (\InvalidArgumentException $e) {
    // "URI is not valid."
}
```

`withStatus()` (called internally) enforces the range `100..599`. So `redirect('/ok', 600)` also throws.

## Conditional redirect helper

```php
use Psr\Http\Message\ResponseInterface;

function redirectIfNotAuthenticated(?User $user, string $loginUrl): ?ResponseInterface {
    if ($user !== null) return null;
    return (new \InitPHP\HTTP\Message\Response())->redirect($loginUrl, 302);
}

// In your controller:
if ($redirect = redirectIfNotAuthenticated($user, '/login')) {
    return $redirect;
}
```

## See also

- [Response](Response.md) — the full producer surface.
- [Recipe — JSON Response](Recipe-JSON-Response.md) — for non-redirect responses.
