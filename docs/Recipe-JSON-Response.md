# Recipe — JSON Response

## With the convenience producer

```php
use InitPHP\HTTP\Message\Response;
use InitPHP\HTTP\Emitter\Emitter;

$response = (new Response())->json(['ok' => true, 'data' => $rows], 200);
(new Emitter())->emit($response);
```

What this does:

- `json_encode(..., JSON_THROW_ON_ERROR)` — unencodable payloads raise `InvalidArgumentException`, never silently produce `false`.
- `Content-Type: application/json; charset=utf-8`.
- Replaces the body with a fresh in-memory string-backed `Stream` carrying the encoded JSON.
- Sets the status to `$status` (default 200).
- Returns a clone — the original `Response` is untouched.

Pass extra flags as the third argument:

```php
$pretty = (new Response())->json($data, 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
```

## Manual variant — custom Content-Type subtype

For media types like `application/vnd.example+json` or `application/problem+json`:

```php
use InitPHP\HTTP\Message\Response;
use InitPHP\HTTP\Message\Stream;

$body = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

$response = (new Response(200, [
    'Content-Type' => 'application/vnd.example+json; charset=utf-8',
]))->withBody(new Stream($body, null));
```

The `null` Stream backend is the cheapest option for short bodies — no resource allocation, just a PHP string. See [Stream](Stream.md).

## RFC 9457 Problem Details

```php
$problem = [
    'type'     => 'https://example.com/probs/invalid-input',
    'title'    => 'Invalid input',
    'status'   => 422,
    'detail'   => 'Field "email" must be a valid address.',
    'instance' => '/users',
];

$response = (new Response(422, [
    'Content-Type' => 'application/problem+json',
]))->withBody(new Stream(
    json_encode($problem, JSON_THROW_ON_ERROR),
    null
));
```

## JSON-streaming large lists

For megabyte-class payloads, prefer streaming over `json_encode` in one shot — otherwise you're materialising the entire response in memory before the first byte hits the wire.

```php
use InitPHP\HTTP\Message\Response;
use InitPHP\HTTP\Message\Stream;
use InitPHP\HTTP\Emitter\Emitter;

$body = fopen('php://temp', 'w+b');
fwrite($body, '[');

$first = true;
foreach ($pdo->query('SELECT * FROM big_table') as $row) {
    if (!$first) fwrite($body, ',');
    fwrite($body, json_encode($row, JSON_THROW_ON_ERROR));
    $first = false;
}

fwrite($body, ']');
rewind($body);

$response = (new Response(200, [
    'Content-Type' => 'application/json; charset=utf-8',
]))->withBody(new Stream($body));

(new Emitter())->emit($response, 65536);  // chunked output
```

## CORS preflight + JSON in one handler

```php
use Psr\Http\Message\ServerRequestInterface;

function jsonHandler(ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface {
    // Echo the Origin back only when it is on your allow-list. Reflecting any
    // Origin is the same as "*", and becomes a hole the moment you add
    // Access-Control-Allow-Credentials. For other origins, send no CORS headers.
    $allowedOrigins = ['https://app.example.com'];
    $origin         = $request->getHeaderLine('Origin');

    $cors = ['Vary' => 'Origin'];
    if (in_array($origin, $allowedOrigins, true)) {
        $cors += [
            'Access-Control-Allow-Origin'  => $origin,
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
        ];
    }

    if ($request->getMethod() === 'OPTIONS') {
        return new \InitPHP\HTTP\Message\Response(204, $cors);
    }

    return (new \InitPHP\HTTP\Message\Response(200, $cors))
        ->json(['ok' => true]);
}
```

## See also

- [Response](Response.md) — the convenience producer's signature and trade-offs.
- [Recipe — Streaming Large Files](Recipe-Streaming-Large-Files.md) — for non-JSON streaming.
