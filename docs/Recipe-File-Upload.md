# Recipe — File Upload

## Server side: receiving uploads

```php
use InitPHP\HTTP\Message\ServerRequest;

$request = ServerRequest::createFromGlobals();

foreach ($request->getUploadedFiles() as $field => $file) {
    if ($file instanceof \Psr\Http\Message\UploadedFileInterface) {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new \DomainException('Upload failed: error #' . $file->getError());
        }
        $file->moveTo('/var/www/uploads/' . bin2hex(random_bytes(8)) . '.bin');
    }
}
```

See [UploadedFile](Uploaded-File.md) for the full `UPLOAD_ERR_*` error map.

## Nested file inputs

PHP represents inputs like `name="docs[parent][child]"` as parallel arrays of `tmp_name`, `size`, `error`, `name`, `type`. `ServerRequest::normalizeFiles()` walks the tree recursively and produces a matching tree of `UploadedFile` values:

```html
<form enctype="multipart/form-data" method="POST">
    <input type="file" name="docs[brief]">
    <input type="file" name="docs[exhibits][a]">
    <input type="file" name="docs[exhibits][b]">
</form>
```

```php
$request = ServerRequest::createFromGlobals();
$tree    = $request->getUploadedFiles();

$tree['docs']['brief']             instanceof UploadedFileInterface; // true
$tree['docs']['exhibits']['a']     instanceof UploadedFileInterface; // true
$tree['docs']['exhibits']['b']     instanceof UploadedFileInterface; // true
```

Walk the tree with a small recursive helper:

```php
function walk(array $files, callable $sink): void {
    foreach ($files as $entry) {
        if ($entry instanceof \Psr\Http\Message\UploadedFileInterface) {
            $sink($entry);
        } elseif (is_array($entry)) {
            walk($entry, $sink);
        }
    }
}

walk($tree, static function (\Psr\Http\Message\UploadedFileInterface $file) {
    if ($file->getError() === UPLOAD_ERR_OK) {
        $file->moveTo('/uploads/' . $file->getClientFilename());
    }
});
```

## Client side: sending a single file (multipart)

PSR-7 has no opinion on `multipart/form-data` encoding — the spec is on the wire format, not the encoding. Build the body string yourself or use a multipart-encoder package, then ship it.

```php
use InitPHP\HTTP\Client\Client;
use InitPHP\HTTP\Message\Request;
use InitPHP\HTTP\Message\Stream;

$boundary = '----' . bin2hex(random_bytes(8));
$payload  = "--{$boundary}\r\n"
          . "Content-Disposition: form-data; name=\"avatar\"; filename=\"me.jpg\"\r\n"
          . "Content-Type: image/jpeg\r\n\r\n"
          . file_get_contents('/path/to/me.jpg') . "\r\n"
          . "--{$boundary}--\r\n";

$request = new Request(
    'POST',
    'https://api.example.com/profile/avatar',
    [
        'Content-Type'   => 'multipart/form-data; boundary=' . $boundary,
        'Content-Length' => (string) strlen($payload),
    ],
    new Stream($payload, 'php://temp')
);

$response = (new Client())->sendRequest($request);
```

## Client side: streaming a large file

For multi-MB uploads, build the body into a temp resource instead of a PHP string:

```php
$boundary = '----' . bin2hex(random_bytes(8));
$tmp = fopen('php://temp', 'w+b');

fwrite($tmp, "--{$boundary}\r\n");
fwrite($tmp, "Content-Disposition: form-data; name=\"upload\"; filename=\"big.bin\"\r\n");
fwrite($tmp, "Content-Type: application/octet-stream\r\n\r\n");
stream_copy_to_stream(fopen('/path/to/big.bin', 'rb'), $tmp);
fwrite($tmp, "\r\n--{$boundary}--\r\n");
rewind($tmp);

$request = new Request('POST', 'https://api.example.com/upload', [
    'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
], new Stream($tmp));

(new Client())->sendRequest($request);
```

## Why no built-in multipart encoder

It's a separate concern with significant subtleties (chunked transfer, RFC-2231 filename encoding, character sets, edge cases for nested data). Keeping the HTTP transport unaware of multipart encoding means you can plug in `guzzlehttp/psr7`'s `MultipartStream`, `symfony/mime`, or hand-rolled bytes — whatever you've already standardised on. The PSR-7 `StreamInterface` is the seam.

## See also

- [UploadedFile](Uploaded-File.md) — the receiving-side type and its `moveTo()` mechanics.
- [ServerRequest](Server-Request.md) — `normalizeFiles()` recursion.
- [Recipe — Streaming Large Files](Recipe-Streaming-Large-Files.md) — for downloads, the mirror direction.
