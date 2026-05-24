<?php
/**
 * UploadedFile.php
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

use \RuntimeException;
use \InvalidArgumentException;
use \Psr\Http\Message\{UploadedFileInterface, StreamInterface};

use const UPLOAD_ERR_OK;
use const UPLOAD_ERR_INI_SIZE;
use const UPLOAD_ERR_FORM_SIZE;
use const UPLOAD_ERR_PARTIAL;
use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_NO_TMP_DIR;
use const UPLOAD_ERR_CANT_WRITE;
use const UPLOAD_ERR_EXTENSION;
use const PHP_SAPI;

use function is_string;
use function is_resource;
use function fopen;
use function sprintf;
use function error_get_last;
use function rename;
use function move_uploaded_file;

/**
 * PSR-7 UploadedFileInterface implementation. Wraps a single uploaded file
 * — supplied as a tmp_name path, a resource handle, or a pre-built
 * StreamInterface — and ships the contract methods {@see getStream()} /
 * {@see moveTo()} plus the metadata accessors PSR-7 mandates. Designed to
 * work both under PHP-FPM (where move_uploaded_file() guards against
 * spoofed uploads) and under the CLI test runner (where rename() is used
 * as the fallback).
 */
class UploadedFile implements UploadedFileInterface
{

    protected const ERRORS = [
        UPLOAD_ERR_OK           => 1,
        UPLOAD_ERR_INI_SIZE     => 1,
        UPLOAD_ERR_FORM_SIZE    => 1,
        UPLOAD_ERR_PARTIAL      => 1,
        UPLOAD_ERR_NO_FILE      => 1,
        UPLOAD_ERR_NO_TMP_DIR   => 1,
        UPLOAD_ERR_CANT_WRITE   => 1,
        UPLOAD_ERR_EXTENSION    => 1,
    ];

    protected ?StreamInterface $stream = null;

    protected ?string $clientFilename;
    protected ?string $clientMediaType;
    protected ?int $error;
    protected ?string $file = null;
    protected bool $moved = false;
    protected ?int $size;

    /**
     * Build an UploadedFile from one of the three input shapes PSR-7
     * accepts.
     *
     * @param  StreamInterface|string|resource $streamOrFile    A tmp_name path, a stream handle, or a pre-built Stream.
     * @param  int|null                        $size            File size in bytes; PSR-7 allows null when fstat() cannot report one.
     * @param  int                             $errorStatus     One of the UPLOAD_ERR_* constants.
     * @param  string|null                     $clientFilename  Client-supplied filename (untrusted).
     * @param  string|null                     $clientMediaType Client-supplied MIME type (untrusted).
     * @throws InvalidArgumentException When $streamOrFile is not one of the supported shapes (and the upload completed without error).
     */
    public function __construct($streamOrFile, ?int $size, int $errorStatus, ?string $clientFilename = null, ?string $clientMediaType = null)
    {
        $this->size = $size;
        $this->error = $errorStatus;
        $this->clientFilename = $clientFilename;
        $this->clientMediaType = $clientMediaType;
        $this->init($streamOrFile);
    }

    /**
     * Release the wrapped state when the instance goes out of scope.
     *
     * @return void
     */
    public function __destruct()
    {
        unset($this->clientFilename, $this->clientMediaType, $this->error, $this->file, $this->size, $this->stream);
        $this->moved = false;
    }

    /**
     * Initialise the backing storage from the constructor input shape.
     * Skipped when the upload reported a non-OK error code; in that case
     * the file is not addressable anyway.
     *
     * @param  StreamInterface|string|resource $streamOrFile
     * @return void
     * @throws InvalidArgumentException When $streamOrFile is not a usable string/resource/StreamInterface.
     */
    protected function init($streamOrFile)
    {
        if($this->error !== UPLOAD_ERR_OK){
            return;
        }
        if(is_string($streamOrFile) && !empty($streamOrFile)){
            $this->file = $streamOrFile;
            return;
        }
        if(is_resource($streamOrFile)){
            $this->stream = new Stream($streamOrFile);
            return;
        }
        if($streamOrFile instanceof StreamInterface){
            $this->stream = $streamOrFile;
            return;
        }
        throw new InvalidArgumentException('Invalid stream or file provided for UploadedFile');
    }

    /**
     * Return the uploaded file as a StreamInterface. When the upload was
     * supplied as a tmp_name path, the file is opened on demand in read
     * mode.
     *
     * @return StreamInterface
     * @throws RuntimeException When the upload errored, was already moved, or the tmp file cannot be opened.
     */
    public function getStream()
    {
        $this->throwHasErrorOrMoved();
        if($this->stream instanceof StreamInterface){
            return $this->stream;
        }
        if(($resource = @fopen($this->file, 'r')) === FALSE){
            throw new RuntimeException(sprintf('The file "%s" cannot be opened: %s', $this->file, error_get_last()['message'] ?? ''));
        }
        return new Stream($resource);
    }

    /**
     * Move the uploaded file to $targetPath. Uses move_uploaded_file()
     * under SAPI runtimes so PHP's anti-spoofing safeguard applies; falls
     * back to rename() under the CLI SAPI; falls back to a chunked copy
     * when the file was supplied as a stream rather than a path.
     *
     * @param  string $targetPath
     * @return void
     * @throws InvalidArgumentException When $targetPath is empty or not a string.
     * @throws RuntimeException         When the upload errored, was already moved, or the move/copy fails.
     */
    public function moveTo($targetPath)
    {
        $this->throwHasErrorOrMoved();
        if(!is_string($targetPath) || $targetPath === ''){
            throw new InvalidArgumentException('Invalid path provided for move operation; must be a non-empty string');
        }
        if($this->file !== null){
            $this->moved = ('cli' === PHP_SAPI) ? @rename($this->file, $targetPath) : @move_uploaded_file($this->file, $targetPath);
            if($this->moved === FALSE){
                throw new RuntimeException(sprintf('Uploaded file could not be moved to "%s": %s', $targetPath, error_get_last()['message'] ?? ''));
            }
        }else{
            $stream = $this->getStream();
            if($stream->isSeekable()){
                $stream->rewind();
            }
            if(($resource = @fopen($targetPath, 'w')) === FALSE){
                throw new RuntimeException(sprintf('The file "%s" cannot be opened: %s', $targetPath, error_get_last()['message'] ?? ''));
            }
            $dest = new Stream($resource);
            while (!$stream->eof()) {
                $chunk = $stream->read(1048576);
                if ($chunk === '') {
                    // Nothing left to copy; avoid an infinite loop if the
                    // upstream stream's eof() never flips.
                    break;
                }
                $written = 0;
                $length = strlen($chunk);
                while ($written < $length) {
                    $delta = $dest->write(substr($chunk, $written));
                    if ($delta <= 0) {
                        throw new RuntimeException('Failed writing uploaded file payload to destination.');
                    }
                    $written += $delta;
                }
            }
            $this->moved = true;
        }
    }

    /**
     * Return the upload size in bytes, or null when unknown.
     *
     * @return int|null
     */
    public function getSize(): ?int
    {
        return $this->size;
    }

    /**
     * Return one of the UPLOAD_ERR_* constants describing the upload status.
     *
     * @return int
     */
    public function getError(): int
    {
        return $this->error;
    }

    /**
     * Return the client-supplied filename (untrusted), or null when none
     * was supplied.
     *
     * @return string|null
     */
    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    /**
     * Return the client-supplied MIME type (untrusted), or null when none
     * was supplied.
     *
     * @return string|null
     */
    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }

    /**
     * Guard against reading or moving a file that errored on upload or
     * has already been moved (PSR-7 forbids a second move).
     *
     * @return void
     * @throws RuntimeException When the upload errored or was already moved.
     */
    protected function throwHasErrorOrMoved(): void
    {
        if($this->error !== UPLOAD_ERR_OK){
            throw new RuntimeException('Cannot retrieve stream due to upload error');
        }
        if($this->moved){
            throw new RuntimeException('Cannot retrieve stream after it has already been moved');
        }
    }

}
