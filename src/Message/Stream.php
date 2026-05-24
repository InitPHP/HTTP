<?php
/**
 * Stream.php
 *
 * This file is part of InitPHP HTTP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\HTTP\Message;

use \Psr\Http\Message\StreamInterface;
use \Throwable;
use \RuntimeException;
use \InvalidArgumentException;

use const SEEK_SET;
use const SEEK_CUR;
use const SEEK_END;

use function is_string;
use function in_array;
use function is_scalar;
use function is_resource;
use function substr;
use function strlen;
use function error_get_last;
use function var_export;
use function stream_get_meta_data;
use function stream_get_contents;
use function fopen;
use function fwrite;
use function fclose;
use function fstat;
use function ftell;
use function feof;
use function rewind;
use function fseek;
use function fread;
use function clearstatcache;

/**
 * PSR-7 StreamInterface implementation supporting three backends in a
 * single class: a real PHP resource handle (php://temp, files, sockets,
 * php://memory) or a plain in-memory string when constructed with a null
 * target. The string backend keeps small bodies allocation-free while
 * still honouring the full seek/read/write contract.
 */
class Stream implements StreamInterface
{
    /**
     * ["php://temp"|"php://memory"|NULL]
     *
     * @var string|null
     */
    protected ?string $target = 'php://temp';

    /** @var resource|string  */
    private $stream;

    protected bool $seekable = false;

    protected bool $readable = false;

    protected bool $writable = false;

    /** @var string|false|null  false = looked up and unavailable, null = not yet looked up */
    protected $uri = null;

    protected ?int $size = null;

    protected int $seek = 0;

    protected const READ_WRITE_HASH = [
        'read' => [
            'r' => true,
            'w+' => true,
            'r+' => true,
            'x+' => true,
            'c+' => true,
            'rb' => true,
            'w+b' => true,
            'r+b' => true,
            'x+b' => true,
            'c+b' => true,
            'rt' => true,
            'w+t' => true,
            'r+t' => true,
            'x+t' => true,
            'c+t' => true,
            'a+' => true,
        ],
        'write' => [
            'w' => true,
            'w+' => true,
            'rw' => true,
            'r+' => true,
            'x+' => true,
            'c+' => true,
            'wb' => true,
            'w+b' => true,
            'r+b' => true,
            'x+b' => true,
            'c+b' => true,
            'w+t' => true,
            'r+t' => true,
            'x+t' => true,
            'c+t' => true,
            'a' => true,
            'a+' => true,
        ],
    ];

    /**
     * Build a stream from a string, resource, StreamInterface or null body.
     *
     * @param  null|string|resource|StreamInterface $body   Initial body contents.
     * @param  string|null                          $target Backing store: "php://temp", "php://memory" or NULL for an in-memory string backend.
     * @throws InvalidArgumentException When $target is invalid or $body cannot be coerced.
     * @throws RuntimeException         When the underlying php:// stream cannot be opened.
     */
    public function __construct($body = '', ?string $target = null)
    {
        $this->init($body, $target);
    }

    /**
     * Read the entire stream into a string. PSR-7 forbids __toString from
     * raising exceptions: any error reading the underlying stream is
     * swallowed and an empty string is returned, mirroring the behaviour
     * of file_get_contents() failure modes.
     *
     * @return string
     */
    public function __toString(): string
    {
        try {
            if ($this->isSeekable()) {
                $this->seek(0);
            }
            return $this->getContents();
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Close the underlying handle (if any) when the stream goes out of scope.
     *
     * @return void
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Deep-clone the underlying resource so the original and the clone do
     * not share a single handle. Without this, every PSR-7 `with*()` call
     * would mutate the body of the original message because both Stream
     * instances point at the same resource.
     *
     * String backends are exempt — PHP's copy-on-write already isolates
     * them at the language level.
     *
     * @return void
     */
    public function __clone()
    {
        if (!isset($this->stream)) {
            return;
        }
        $this->uri = null;
        if (is_string($this->stream)) {
            // String backend is already PHP copy-on-write; nothing to do.
            return;
        }
        if (!is_resource($this->stream)) {
            return;
        }
        $originalPosition = @ftell($this->stream);
        if ($this->seekable) {
            @rewind($this->stream);
        }
        $contents = @stream_get_contents($this->stream);
        if ($contents === false) {
            $contents = '';
        }
        if ($this->seekable && is_int($originalPosition)) {
            @fseek($this->stream, $originalPosition);
        }
        $copy = @fopen('php://temp', 'w+b');
        if ($copy === false) {
            return;
        }
        if ($contents !== '') {
            fwrite($copy, $contents);
        }
        // Preserve the original stream's cursor position.
        if (is_int($originalPosition)) {
            fseek($copy, $originalPosition);
        } else {
            rewind($copy);
        }
        $this->stream = $copy;
        $this->size = strlen($contents);
        $this->seekable = true;
        $this->readable = true;
        $this->writable = true;
    }

    /**
     * (Re)initialise the stream against a new body/target pair. Called by
     * the constructor; can also be used to re-seat an existing instance.
     *
     * @param  null|string|resource|StreamInterface $body
     * @param  string|null                          $target ["php://temp"|"php://memory"|NULL]
     * @return StreamInterface
     * @throws InvalidArgumentException When $target is invalid, or $body cannot be coerced to a string for the in-memory backend.
     * @throws RuntimeException         When the underlying php:// stream cannot be opened.
     */
    public function init($body = null, ?string $target = 'php://temp'): StreamInterface
    {
        if(in_array($target, ['php://temp', 'php://memory', null], true) === FALSE){
            throw new InvalidArgumentException('The target for the stream can only be "php://temp", "php://memory" or NULL.');
        }
        $this->target = $target;
        if($body === null){
            $body = '';
        }
        if($body instanceof StreamInterface){
            if($body->isSeekable()){
                $body->rewind();
            }
            $body = $body->getContents();
        }
        // Resources are always accepted regardless of $target.
        if(is_resource($body)){
            $this->stream = $body;
            $meta = stream_get_meta_data($this->stream);
            $this->seekable = $meta['seekable'] && fseek($this->stream, 0, SEEK_CUR) === 0;
            $this->readable = isset(self::READ_WRITE_HASH['read'][$meta['mode']]);
            $this->writable = isset(self::READ_WRITE_HASH['write'][$meta['mode']]);
            return $this;
        }
        if($this->target === null){
            if(!is_scalar($body)){
                throw new InvalidArgumentException("The parameter \$body must be a string.");
            }
            $this->stream = (string)$body;
            $this->seek = 0;
            $this->size = strlen($this->stream);
            $this->readable = true;
            $this->writable = true;
            $this->seekable = true;
            return $this;
        }
        if(is_string($body)){
            $resource = @fopen($this->target, 'w+b');
            if($resource === false){
                throw new RuntimeException(sprintf('Unable to open stream "%s": %s', $this->target, error_get_last()['message'] ?? ''));
            }
            if($body !== ''){
                fwrite($resource, $body);
                fseek($resource, 0);
            }
            $this->stream = $resource;
            $meta = stream_get_meta_data($this->stream);
            $this->seekable = $meta['seekable'] && fseek($this->stream, 0, SEEK_CUR) === 0;
            $this->readable = isset(self::READ_WRITE_HASH['read'][$meta['mode']]);
            $this->writable = isset(self::READ_WRITE_HASH['write'][$meta['mode']]);
            return $this;
        }
        throw new InvalidArgumentException("The parameter \$body must be a string or a resource.");
    }

    /**
     * Close the stream and release the underlying handle. Subsequent reads
     * or writes will raise RuntimeException.
     *
     * @return void
     */
    public function close()
    {
        if(isset($this->stream)){
            if(is_resource($this->stream)){
                fclose($this->stream);
            }
            unset($this->stream);
            $this->size = null;
            $this->uri = null;
            $this->readable = false;
            $this->writable = false;
            $this->seekable = false;
        }
    }

    /**
     * Detach the underlying handle from this Stream and return it. The
     * Stream is left in an unusable state; the string backend is
     * materialised into a php://memory resource before being returned so
     * the caller always receives a resource handle.
     *
     * @return resource|null
     * @throws RuntimeException When the string backend cannot be materialised.
     */
    public function detach()
    {
        if(!isset($this->stream)){
            return null;
        }
        $res = $this->stream;
        unset($this->stream);
        $this->size = null;
        $this->uri = null;
        $this->readable = false;
        $this->writable = false;
        $this->seekable = false;
        return is_string($res) ? $this->stringToResource($res) : $res;
    }

    /**
     * Return the body size in bytes, or null when the size is unknown
     * (e.g. non-seekable resources without a usable fstat() entry).
     *
     * @return int|null
     */
    public function getSize(): ?int
    {
        if($this->size !== null){
            return $this->size;
        }
        if(!isset($this->stream)){
            return null;
        }
        if(is_string($this->stream)){
            return $this->size = strlen($this->stream);
        }
        if(($uri = $this->getUri())){
            clearstatcache(true, $uri);
        }
        $stats = fstat($this->stream);
        if(isset($stats['size'])){
            return $this->size = $stats['size'];
        }
        return null;
    }

    /**
     * Return the current cursor position in the stream.
     *
     * @return int
     * @throws RuntimeException When the stream is detached or ftell() fails.
     */
    public function tell(): int
    {
        if(!isset($this->stream)){
            throw new RuntimeException('Stream is detached');
        }
        if(is_string($this->stream)){
            return $this->seek;
        }
        if(($result = @ftell($this->stream)) === FALSE){
            throw new RuntimeException('Unable to determine stream position: ' . (error_get_last()['message'] ?? ''));
        }
        return $result;
    }

    /**
     * True when the cursor is at end-of-stream. Returns false for detached
     * streams so callers cannot accidentally treat detachment as EOF.
     *
     * @return bool
     */
    public function eof(): bool
    {
        if(!isset($this->stream)){
            return false;
        }
        if(is_string($this->stream)){
            if($this->size === null){
                $this->size = strlen($this->stream);
            }
            return $this->size <= $this->seek;
        }
        return feof($this->stream);
    }

    /**
     * True when the stream supports random-access seeking.
     *
     * @return bool
     */
    public function isSeekable(): bool
    {
        return $this->seekable;
    }

    /**
     * Move the cursor to $offset using the supplied $whence (SEEK_SET,
     * SEEK_CUR or SEEK_END).
     *
     * @param  int $offset
     * @param  int $whence
     * @return void
     * @throws RuntimeException When the stream is detached, not seekable, or fseek() fails.
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        if(!isset($this->stream)){
            throw new RuntimeException('Stream is detached');
        }
        if(!$this->seekable){
            throw new RuntimeException('Stream is not seekable');
        }
        if(is_string($this->stream)){
            $this->str_seek($offset, $whence);
            return;
        }
        if(-1 === fseek($this->stream, $offset, $whence)){
            throw new RuntimeException('Unable to seek to stream position "'.$offset.'" with whence ' . var_export($whence, true));
        }
    }

    /**
     * Move the cursor back to the start of the stream.
     *
     * @return void
     * @throws RuntimeException When the stream is detached or not seekable.
     */
    public function rewind()
    {
        $this->seek(0);
    }

    /**
     * True when the stream supports being written to.
     *
     * @return bool
     */
    public function isWritable(): bool
    {
        return $this->writable;
    }

    /**
     * Write $string at the current cursor position and return the number of
     * bytes written. The in-memory string backend mirrors fwrite() semantics:
     * appending past EOF extends the buffer; writing in the middle overwrites
     * the slice in place.
     *
     * @param  string $string
     * @return int
     * @throws RuntimeException         When the stream is detached, not writable, or the underlying fwrite() fails.
     * @throws InvalidArgumentException When $string is not a string.
     */
    public function write($string): int
    {
        if(!isset($this->stream)){
            throw new RuntimeException('Stream is detached');
        }
        if(!$this->writable){
            throw new RuntimeException('Cannot write to a non-writable stream');
        }
        if(!is_string($string)){
            throw new InvalidArgumentException('Only strings can be written to the stream.');
        }
        if (is_string($this->stream)) {
            if ($this->size === null) {
                $this->size = strlen($this->stream);
            }
            $written = strlen($string);
            if ($this->seek >= $this->size) {
                // Append past EOF; fseek-style POSIX semantics would zero-pad,
                // but PSR-7 callers never rely on that and PHP's fwrite on a
                // text stream simply extends — match that.
                $this->stream .= $string;
            } else {
                // Overwrite from the current position, exactly like fwrite()
                // on a seekable real stream.
                $this->stream = substr($this->stream, 0, $this->seek)
                    . $string
                    . substr($this->stream, $this->seek + $written);
            }
            $this->seek += $written;
            $this->size = strlen($this->stream);
            return $written;
        }
        $this->size = null;
        if(($result = @fwrite($this->stream, $string)) === FALSE){
            throw new RuntimeException('Unable to write to stream: ' . (error_get_last()['message'] ?? ''));
        }
        return $result;
    }

    /**
     * True when the stream supports being read from.
     *
     * @return bool
     */
    public function isReadable(): bool
    {
        return $this->readable;
    }

    /**
     * Read up to $length bytes from the stream and advance the cursor by
     * the number of bytes actually returned.
     *
     * @param  int $length
     * @return string
     * @throws RuntimeException When the stream is detached, not readable, or fread() fails.
     */
    public function read($length): string
    {
        if(!isset($this->stream)){
            throw new RuntimeException('Stream is detached');
        }
        if(!$this->readable){
            throw new RuntimeException('Cannot read from non-readable stream');
        }
        if(is_string($this->stream)){
            $res = substr($this->stream, $this->seek, $length);
            $this->seek += $length;
            return $res;
        }
        if(($result = @fread($this->stream, $length)) === FALSE){
            throw new RuntimeException('Unable to read from stream: ' . (error_get_last()['message'] ?? ''));
        }
        return $result;
    }

    /**
     * Read everything from the current cursor position to end-of-stream
     * and return it as a string.
     *
     * @return string
     * @throws RuntimeException When the stream is detached or stream_get_contents() fails.
     */
    public function getContents(): string
    {
        if(!isset($this->stream)){
            throw new RuntimeException('Stream is detached');
        }
        if(is_string($this->stream)){
            return $this->stream;
        }
        if(($res = @stream_get_contents($this->stream)) === FALSE){
            throw new RuntimeException('Unable to read stream content: ' . (error_get_last()['message'] ?? ''));
        }
        return $res;
    }

    /**
     * Return stream metadata. With no key, returns the entire metadata
     * array; with a key, returns that single entry or null when absent.
     *
     * @param  string|null $key
     * @return mixed
     */
    public function getMetadata($key = null)
    {
        if(!isset($this->stream)){
            return $key ? null : [];
        }
        if(is_string($this->stream)){
            $data = [
                'uri'       => null,
                'seekable'  => false,
                'eof'       => $this->size === $this->seek
            ];
        }else{
            $data = stream_get_meta_data($this->stream);
        }
        if($key === null){
            return $data;
        }
        return $data[$key] ?? null;
    }

    /**
     * Returns true only when the underlying stream is known to contain zero
     * bytes. A size of null (pipes, sockets, on-the-fly responses) is treated
     * as "indeterminate" — both isEmpty() and isNotEmpty() return false in
     * that case so callers can branch defensively.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        $size = $this->getSize();
        return $size !== null && $size < 1;
    }

    /**
     * Counterpart of {@see Stream::isEmpty()}: only returns true when the
     * size is known and strictly positive.
     *
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        $size = $this->getSize();
        return $size !== null && $size > 0;
    }

    /**
     * Return the resource's URI (file path, php:// stream name, ...) when
     * available, caching the result so getMetadata() is only consulted
     * once per stream.
     *
     * @return string|null
     */
    protected function getUri()
    {
        if($this->uri === null){
            $this->uri = $this->getMetadata('uri') ?? false;
        }
        return $this->uri === false ? null : $this->uri;
    }

    /**
     * Materialise the in-memory string backend into a real php://memory
     * resource handle when the caller asks to detach. The cursor of the new
     * handle is preserved at the same offset as the in-memory cursor (the
     * caller observes the stream they were already using, just as a resource).
     *
     * @param  string $string
     * @return resource
     * @throws RuntimeException When php://memory cannot be opened.
     */
    private function stringToResource(string $string)
    {
        $stream = fopen('php://memory', 'r+b');
        if ($stream === false) {
            throw new RuntimeException('Unable to open php://memory for stream detach.');
        }
        if ($string !== '') {
            fwrite($stream, $string);
        }
        $position = $this->seek;
        if ($position < 0) {
            $position = 0;
        }
        $length = strlen($string);
        if ($position > $length) {
            $position = $length;
        }
        fseek($stream, $position);
        return $stream;
    }

    /**
     * fseek()-compatible seek implementation for the in-memory string
     * backend. Honours SEEK_SET / SEEK_CUR / SEEK_END and clamps the
     * resulting cursor into the [0, size] range.
     *
     * @param  int $offset
     * @param  int $whence
     * @return void
     */
    private function str_seek($offset, $whence = SEEK_SET)
    {
        if($this->size === null){
            $this->size = strlen($this->stream);
        }
        if($whence === SEEK_SET){
            if($offset > $this->size){
                $offset = $this->size;
            }
            $this->seek = $offset;
            return;
        }
        if($whence === SEEK_CUR){
            $this->seek += $offset;
        }
        if($whence === SEEK_END){
            $this->seek = $this->size + $offset;
        }
        if($this->seek < 0){
            $this->seek = 0;
        }elseif($this->seek > $this->size){
            $this->seek = $this->size;
        }
    }

}
