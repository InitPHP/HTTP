# Recipe — File Upload

## Server side: receiving uploads

Treat everything the client sends about a file as untrusted input: its name, its media type and its extension. The helper below stores an upload under a name the **server** generates, with an extension taken from an allow-list that is keyed by the type detected from the file's **content**.

```php
use Psr\Http\Message\UploadedFileInterface;

/**
 * Stores one upload and returns the server-generated file name.
 *
 * @param array<string, string> $allowed Detected media type => extension to store it with.
 */
function storeUpload(UploadedFileInterface $file, string $directory, array $allowed, int $maxBytes): string
{
    if ($file->getError() !== UPLOAD_ERR_OK) {
        throw new \DomainException('Upload failed: error #' . $file->getError());
    }
    if ($file->getSize() === null) {
        throw new \DomainException('The upload size is unknown.');
    }
    if ($file->getSize() > $maxBytes) {
        throw new \DomainException('The upload is too large.');
    }

    // Detect the type from the bytes. getClientMediaType() is just a header the client chose.
    // An upload built from a stream may have been read already, and a stream that cannot be
    // rewound cannot be checked and then stored intact, so it is rejected rather than skipped.
    $stream = $file->getStream();
    if (!$stream->isSeekable()) {
        throw new \DomainException('This upload cannot be inspected.');
    }
    $stream->rewind();
    $head = $stream->read(8192);
    $stream->rewind(); // moveTo() copies from the start; leave the stream there
    $type = (new \finfo(FILEINFO_MIME_TYPE))->buffer($head);
    if ($type === false || !isset($allowed[$type])) {
        throw new \DomainException('This file type is not allowed.');
    }

    // Random name, extension from the allow-list. Nothing here comes from the client.
    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$type];
    $file->moveTo(rtrim($directory, '/') . '/' . $name);

    return $name;
}
```

Use it on the request:

```php
use InitPHP\HTTP\Message\ServerRequest;

$request = ServerRequest::createFromGlobals();

$allowed = [
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png',
    'application/pdf' => 'pdf',
];

foreach ($request->getUploadedFiles() as $field => $file) {
    if ($file instanceof \Psr\Http\Message\UploadedFileInterface) {
        $storedName = storeUpload($file, '/var/uploads', $allowed, 5 * 1024 * 1024);
        // Save $storedName (and, if you need it, the original name as plain data) in your database.
    }
}
```

See [UploadedFile](Uploaded-File.md) for the full `UPLOAD_ERR_*` error map.

### Why it is written this way

- **Never build the path from `getClientFilename()`.** The name is whatever the client sent. A value such as `shell.php` lets the client pick an extension your web server may execute, or overwrite an existing file with the same name; a path such as `../../public/index.php` can choose where the file lands. (PHP's own `$_FILES` handling strips directories from `name`, but a request built from another source may not, and the extension and overwrite risks remain.) A random name removes all of them.
- **Never trust `getClientMediaType()`.** It is the `Content-Type` the client declared for that part, so it can say `image/png` about anything. The helper reads the first bytes of the file and asks `finfo` instead. Detection by content is a safeguard, not a guarantee that a file is harmless: still store uploads in a directory your web server does not execute code from, ideally outside the document root, and serve them through your own handler.
- **The extension comes from the allow-list, not from the client.** A type that is not in `$allowed` is rejected, and an accepted type is always stored with the extension you mapped to it.
- **The original name is data, not a path.** If you want to show it to users later, keep it in your database and escape it on output.
- **The stream is rewound before and after the check.** An upload built from a stream may already have been read, and `moveTo()` copies from the start. Checking and storing the same bytes matters, so a stream that cannot be rewound is rejected instead of being checked partially. Uploads from `ServerRequest::createFromGlobals()` are backed by a file path and open a fresh stream on every `getStream()` call.
- **Size is checked too.** `$maxBytes` is your application limit. PHP's own `upload_max_filesize` and `post_max_size` still apply and surface as `UPLOAD_ERR_*` errors.

`finfo` needs the `fileinfo` extension, which is bundled with PHP and enabled by default.

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

Walk the tree with a small recursive helper, and store each file with the `storeUpload()` function from above:

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

walk($tree, static function (\Psr\Http\Message\UploadedFileInterface $file) use ($allowed) {
    if ($file->getError() === UPLOAD_ERR_OK) {
        $storedName = storeUpload($file, '/var/uploads', $allowed, 5 * 1024 * 1024);
        // Save $storedName in your database, otherwise nothing links the stored file to its owner.
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
