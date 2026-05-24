# Recipe: File Uploads

## Server side — receiving

```php
use InitPHP\HTTP\Message\ServerRequest;

$request = ServerRequest::createFromGlobals();

foreach ($request->getUploadedFiles() as $field => $file) {
    if ($file instanceof \Psr\Http\Message\UploadedFileInterface) {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            // Reject early — see docs/psr7/uploaded-files.md for the error map.
            throw new \DomainException('Upload failed.');
        }
        $file->moveTo('/var/www/uploads/' . bin2hex(random_bytes(8)) . '.bin');
    }
}
```

For nested input names (`docs[brief]`, `docs[exhibits][a]`), walk the tree recursively — see [Uploaded Files](../psr7/uploaded-files.md#nested-file-inputs).

## Client side — sending a single file

PSR-7 has no opinion on how you encode `multipart/form-data` — the spec is on the wire format, not the encoding. Build the body string yourself or use a multipart-encoder package, then ship it:

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

(new Client())->sendRequest($request);
```

For multi-megabyte uploads stream the file into a temp resource instead of building the payload in memory:

```php
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

It's a separate concern with significant subtleties (chunked transfer, RFC-2231 filename encoding, character sets, edge cases for nested data). Keeping the HTTP transport unaware of multipart encoding means you can plug in `guzzlehttp/psr7`'s `MultipartStream`, `symfony/mime`, or hand-rolled bytes — whatever you already standardised on.
