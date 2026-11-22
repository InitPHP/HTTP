# UploadedFile

`InitPHP\HTTP\Message\UploadedFile` implements `Psr\Http\Message\UploadedFileInterface`. It represents a single uploaded file — either materialised from `$_FILES` by `ServerRequest::normalizeFiles()`, or constructed by hand from a `Stream` for tests.

```php
use InitPHP\HTTP\Message\UploadedFile;
```

## Construction

```php
new UploadedFile(
    string|resource|StreamInterface $streamOrFile,
    ?int $size,                     // bytes — null is valid when fstat() can't report
    int $errorStatus,               // UPLOAD_ERR_OK, UPLOAD_ERR_INI_SIZE, ...
    ?string $clientFilename = null,
    ?string $clientMediaType = null
);
```

Three input shapes are accepted for `$streamOrFile`:

| Input               | Behaviour                                                                                  |
|---------------------|--------------------------------------------------------------------------------------------|
| `string`            | Treated as a path; `getStream()` will `fopen()` it on first call.                          |
| `resource`          | Wrapped in a `Stream` immediately.                                                          |
| `StreamInterface`   | Stored verbatim and returned from `getStream()`.                                            |

When `$errorStatus !== UPLOAD_ERR_OK` the stream/file argument is ignored — the upload failed, there's nothing to read.

```php
// From $_FILES (production)
$file = new UploadedFile(
    '/tmp/php-abc123',
    1024,
    UPLOAD_ERR_OK,
    'me.png',
    'image/png'
);

// From a Stream (tests / synthetic data)
use InitPHP\HTTP\Message\Stream;
$file = new UploadedFile(
    new Stream('synthetic content', 'php://temp'),
    17,
    UPLOAD_ERR_OK,
    'synth.txt',
    'text/plain'
);
```

## Inspection

```php
$file->getSize();              // ?int  — may be null per PSR-7
$file->getError();             // int   — one of UPLOAD_ERR_*
$file->getClientFilename();    // ?string
$file->getClientMediaType();   // ?string
$file->getStream();            // StreamInterface  — throws if errored or moved
```

> **v2 → v3 note:** `getSize()` is now `?int` to match the PSR-7 contract. The constructor's `$size` parameter is also `?int`. If your code did `int $size = $file->getSize()`, add a null check.

## `moveTo($targetPath)`

```php
$file->moveTo('/var/www/uploads/' . bin2hex(random_bytes(8)) . '.bin');
```

`moveTo()` picks the right primitive based on the SAPI:

- **CLI** — uses `rename()`. PHP's `is_uploaded_file()` safety net is meaningless under the CLI; `rename()` is the correct choice.
- **Web SAPIs** (FPM, mod_php, ...) — uses `move_uploaded_file()`, which fails safely if the source isn't actually one of the request's uploads.
- **Stream-backed UploadedFile** (constructed from a `Stream`, not a file path) — opens the target with `fopen('wb')` and copies the source via `Stream::read()` / `Stream::write()` in **1 MiB chunks**. Partial writes are retried until the whole chunk is flushed; persistent failures throw `RuntimeException`.

After a successful move, the upload is consumed:

```php
$file->getStream();                   // throws RuntimeException
$file->moveTo('/elsewhere');          // throws RuntimeException
```

`moveTo('')` raises `InvalidArgumentException`.

## Error states

Every PHP upload error (`UPLOAD_ERR_*`) has a meaning; the canonical user-facing mapping you might want:

```php
$errorMessages = [
    UPLOAD_ERR_OK         => null,
    UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize.',
    UPLOAD_ERR_FORM_SIZE  => 'File exceeds the form-level MAX_FILE_SIZE.',
    UPLOAD_ERR_PARTIAL    => 'Upload was interrupted.',
    UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
    UPLOAD_ERR_NO_TMP_DIR => 'Server is missing a temporary directory.',
    UPLOAD_ERR_CANT_WRITE => 'Server could not write the file to disk.',
    UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload.',
];

if ($file->getError() !== UPLOAD_ERR_OK) {
    throw new \DomainException($errorMessages[$file->getError()] ?? 'Unknown upload error.');
}
```

Calling `getStream()` or `moveTo()` on an errored upload always throws `RuntimeException`.

## Nested file inputs

PHP represents inputs like `name="docs[parent][child]"` as parallel arrays of `tmp_name`, `size`, `error`, `name`, `type`. `ServerRequest::normalizeFiles()` walks the tree recursively and returns a matching tree of `UploadedFile` values — see [ServerRequest](Server-Request.md) and [Recipe — File Upload](Recipe-File-Upload.md).

## See also

- [Recipe — File Upload](Recipe-File-Upload.md) — receiving + sending multipart bodies.
- [ServerRequest](Server-Request.md) — `normalizeFiles()` and the `$_FILES` tree shape.
- [Stream](Stream.md) — used internally by the stream-backed `moveTo()` copy path.
