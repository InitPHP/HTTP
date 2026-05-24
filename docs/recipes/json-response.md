# Recipe: JSON Responses

## With the convenience producer

```php
use InitPHP\HTTP\Message\Response;
use InitPHP\HTTP\Emitter\Emitter;

$response = (new Response())->json(['ok' => true, 'data' => $rows], 200);

(new Emitter())->emit($response);
```

Wraps `json_encode($data, JSON_THROW_ON_ERROR)`, sets `Content-Type: application/json; charset=utf-8`, and applies the status. An unencodable payload throws `InvalidArgumentException` — no silent `false` bodies.

Pass extra flags as the third argument:

```php
$pretty = (new Response())->json($data, 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
```

## Manual variant

If you need a custom Content-Type subtype (`application/vnd.example+json`, `application/problem+json`, …):

```php
use InitPHP\HTTP\Message\Stream;

$body = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
$response = (new Response(200, [
    'Content-Type' => 'application/vnd.example+json; charset=utf-8',
]))->withBody(new Stream($body, null));
```

The `null` stream backend is the cheapest option for short bodies — no resource allocation, just a PHP string.

## RFC 9457 Problem Details

```php
$problem = [
    'type'   => 'https://example.com/probs/invalid-input',
    'title'  => 'Invalid input',
    'status' => 422,
    'detail' => 'Field "email" must be a valid address.',
    'instance' => '/users',
];

$body = json_encode($problem, JSON_THROW_ON_ERROR);
$response = (new Response(422, [
    'Content-Type' => 'application/problem+json',
]))->withBody(new Stream($body, null));
```

## JSON-streaming large lists

For megabyte-class payloads, prefer streaming over `json_encode` in one shot:

```php
$body = fopen('php://temp', 'w+b');
fwrite($body, '[');
$first = true;
foreach ($cursor as $row) {
    if (!$first) fwrite($body, ',');
    fwrite($body, json_encode($row, JSON_THROW_ON_ERROR));
    $first = false;
}
fwrite($body, ']');
rewind($body);

$response = (new Response(200, ['Content-Type' => 'application/json; charset=utf-8']))
    ->withBody(new Stream($body));

(new Emitter())->emit($response, 65536); // chunked output
```
