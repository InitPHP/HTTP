<?php
/**
 * UploadedFile.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    http://initphp.github.com/license.txt  MIT
 * @version    1.0.3
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\HTTP;

use \Psr\Http\Message\{StreamInterface, UploadedFileInterface};

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
     * @param StreamInterface|string|resource $streamOrFile
     * @param int $size
     * @param int $errorStatus
     * @param string|null $clientFilename
     * @param string|null $clientMediaType
     */
    public function __construct($streamOrFile, int $size, int $errorStatus, ?string $clientFilename = null, ?string $clientMediaType = null)
    {
        $this->size = $size;
        $this->error = $errorStatus;
        $this->clientFilename = $clientFilename;
        $this->clientMediaType = $clientMediaType;
        $this->init($streamOrFile);
    }

    public function __destruct()
    {
        unset($this->clientFilename, $this->clientMediaType, $this->error, $this->file, $this->size, $this->stream);
        $this->moved = false;
    }

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
        throw new \InvalidArgumentException('Invalid stream or file provided for UploadedFile');
    }

    /**
     * @inheritDoc
     */
    public function getStream()
    {
        $this->throwHasErrorOrMoved();
        if($this->stream instanceof StreamInterface){
            return $this->stream;
        }
        if(($resource = @fopen($this->file, 'r')) === FALSE){
            throw new \RuntimeException(sprintf('The file "%s" cannot be opened: %s', $this->file, error_get_last()['message'] ?? ''));
        }
        return new Stream($resource);
    }

    /**
     * @inheritDoc
     */
    public function moveTo($targetPath)
    {
        $this->throwHasErrorOrMoved();
        if(!is_string($targetPath) || $targetPath === ''){
            throw new \InvalidArgumentException('Invalid path provided for move operation; must be a non-empty string');
        }
        if($this->file !== null){
            $this->moved = ('cli' === PHP_SAPI) ? @rename($this->file, $targetPath) : @move_uploaded_file($this->file, $targetPath);
            if($this->moved === FALSE){
                throw new \RuntimeException(sprintf('Uploaded file could not be moved to "%s": %s', $targetPath, error_get_last()['message'] ?? ''));
            }
        }else{
            $stream = $this->getStream();
            if($stream->isSeekable()){
                $stream->rewind();
            }
            if(($resource = @fopen($targetPath, 'w')) === FALSE){
                throw new \RuntimeException(sprintf('The file "%s" cannot be opened: %s', $targetPath, \error_get_last()['message'] ?? ''));
            }
            $dest = new Stream($resource);
            while (!$stream->eof()) {
                if(!$dest->write($stream->read(1048576))){
                    break;
                }
            }
            $this->moved = true;
        }
    }

    /**
     * @inheritDoc
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * @inheritDoc
     */
    public function getError(): int
    {
        return $this->error;
    }

    /**
     * @inheritDoc
     */
    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    /**
     * @inheritDoc
     */
    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }

    protected function throwHasErrorOrMoved(): void
    {
        if($this->error !== UPLOAD_ERR_OK){
            throw new \RuntimeException('Cannot retrieve stream due to upload error');
        }
        if($this->moved){
            throw new \RuntimeException('Cannot retrieve stream after it has already been moved');
        }
    }

}
