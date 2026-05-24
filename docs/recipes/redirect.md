# Recipe: Redirects

## Permanent (301)

```php
use InitPHP\HTTP\Message\Response;

$response = (new Response())->redirect('https://example.com/new-home', 301);
```

Sets `Location: https://example.com/new-home` and status 301. Use 301 for SEO-relevant moves that you want cached by search engines and browsers.

## Temporary (302 / 307)

```php
$response = (new Response())->redirect('/dashboard', 302);
```

302 (Found) is what most "after login, send the user back" flows use. If you specifically need the client to preserve the request method (e.g. POST → POST), use 307 (Temporary Redirect):

```php
$response = (new Response())->redirect('/checkout', 307);
```

## See Other (303) — POST/Redirect/GET

After processing a POST, send 303 to force the browser to do a GET on the next URL — the classic anti-double-submit pattern:

```php
$response = (new Response())->redirect('/orders/' . $orderId, 303);
```

## With a delay

For "thanks, redirecting in 5 seconds" pages:

```php
$response = (new Response())->redirect('https://example.com/thanks', 200, 5);
```

`Location` is **still** set (for crawlers and non-browser clients) **plus** `Refresh: 5; url=https://example.com/thanks` for browsers that honour the countdown. The status defaults to 302 if you don't pass one; the example above uses 200 so the page body actually renders during the wait.

## Sticky session preservation

PSR-7 leaves cookie handling to your session middleware; if you need to preserve a session across the redirect, set it on the response before emitting:

```php
$response = (new Response())
    ->redirect('/dashboard', 302)
    ->withAddedHeader('Set-Cookie', 'PHPSESSID=' . session_id() . '; Path=/; HttpOnly');
```

## Validation

`redirect()` throws `InvalidArgumentException` if you pass anything that isn't a string or a `Psr\Http\Message\UriInterface`:

```php
try {
    $response = (new Response())->redirect(42);
} catch (\InvalidArgumentException $e) {
    // "URI is not valid."
}
```
