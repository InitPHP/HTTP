# Uploaded Files

`InitPHP\HTTP\Message\UploadedFile` implements `Psr\Http\Message\UploadedFileInterface`. The two consumer use-cases are:

1. **Inside a controller** — iterate `ServerRequest::getUploadedFiles()` and move each upload to its final home.
2. **In tests** — build an `UploadedFile` directly from a `Stream` to simulate an incoming file without touching `$_FILES`.

## Constructing manually

```php
use InitPHP\HTTP\Message\UploadedFile;
use InitPHP\HTTP\Message\Stream;

$file = new UploadedFile(
    new Stream('file contents', 'php://temp'),
    13,                  // size in bytes — may be null when unknown
    UPLOAD_ERR_OK,       // one of the UPLOAD_ERR_* constants
    'manifesto.txt',     // client-supplied filename
    'text/plain'         // client-supplied media type
);
```

You can also pass a file path string or an open resource as the first argument; `UploadedFile` wraps it into a Stream on first `getStream()` call.

## Inspection

```php
$file->getSize();              // int|null
$file->getError();             // int — one of the UPLOAD_ERR_* constants
$file->getClientFilename();    // string|null
$file->getClientMediaType();   // string|null
$file->getStream();            // StreamInterface (throws if moved or upload errored)
```

## Moving the file

```php
$file->moveTo('/var/www/uploads/manifesto.txt');
```

`moveTo()` selects the right primitive based on the SAPI:

- **CLI** (tests, scripts) — uses `rename()` (no `is_uploaded_file()` requirement).
- **Web SAPIs** — uses `move_uploaded_file()` to satisfy PHP's upload safety check.
- **Stream-backed UploadedFile** (e.g. constructed from a `Stream` in tests) — copies via `Stream::read()`/`Stream::write()` in 1 MiB chunks, looping until all bytes are flushed (partial writes are retried).

After a successful move, the upload is consumed:

```php
$file->getStream();   // throws RuntimeException — "after it has already been moved"
$file->moveTo('/elsewhere'); // throws too
```

## Nested file inputs

PHP's `$_FILES` represents nested form names like `file[parent][child]` as parallel arrays of `tmp_name`, `size`, `error`, `name`, `type`. `ServerRequest::normalizeFiles()` walks the tree recursively and produces a matching tree of `UploadedFile` values:

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

walk($tree, static fn ($file) => $file->moveTo('/uploads/' . $file->getClientFilename()));
```

## Error handling

`getError()` returns one of PHP's `UPLOAD_ERR_*` constants. Anything other than `UPLOAD_ERR_OK` means the upload failed and `getStream()` / `moveTo()` will throw `RuntimeException`. Build a small mapping table for user-facing messages:

```php
$errors = [
    UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize.',
    UPLOAD_ERR_FORM_SIZE  => 'File exceeds the form-level MAX_FILE_SIZE.',
    UPLOAD_ERR_PARTIAL    => 'Upload was interrupted.',
    UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
    UPLOAD_ERR_NO_TMP_DIR => 'Server is missing a temporary directory.',
    UPLOAD_ERR_CANT_WRITE => 'Server could not write the file to disk.',
    UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload.',
];

if ($file->getError() !== UPLOAD_ERR_OK) {
    throw new \DomainException($errors[$file->getError()] ?? 'Unknown upload error.');
}
```
