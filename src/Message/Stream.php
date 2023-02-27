<?php
/**
 * Stream.php
 *
 * This file is part of InitPHP HTTP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    2.0
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\HTTP\Message;

use InitPHP\HTTP\Message\Interfaces\StreamInterface;
use \Throwable;
use \RuntimeException;
use \InvalidArgumentException;

use const E_USER_ERROR;
use const PHP_VERSION_ID;
use const SEEK_SET;
use const SEEK_CUR;
use const SEEK_END;

use function is_string;
use function is_array;
use function in_array;
use function is_scalar;
use function is_resource;
use function substr;
use function strlen;
use function set_error_handler;
use function restore_error_handler;
use function trigger_error;
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

    protected ?bool $seekable;

    protected ?bool $readable;

    protected ?bool $writable;

    protected $uri;

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

    public function __construct($body = '', ?string $target = null)
    {
        $this->init($body, $target);
    }

    /**
     * @inheritDoc
     */
    public function __toString()
    {
        try{
            if(is_resource($this->stream) && $this->isSeekable()){
                $this->seek(0);
            }
            return $this->getContents();
        }catch (Throwable $e){
            if(PHP_VERSION_ID >= 70400){
                throw $e;
            }
            if(is_array($errorHandler = set_error_handler('var_dump'))){
                $errorHandler = $errorHandler[0] ?? null;
            }
            restore_error_handler();
            if($errorHandler instanceof \Error){
                return trigger_error((string)$e, E_USER_ERROR);
            }
            return '';
        }
    }

    /**
     * @inheritDoc
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * @param null|string|resource|StreamInterface $body
     * @param string|null $target <p>["php://temp"|"php://memory"|NULL]</p>
     * @return StreamInterface
     * @throws InvalidArgumentException
     */
    public function init($body = null, ?string $target = 'php://temp'): StreamInterface
    {
        if($body instanceof StreamInterface){
            return $body;
        }
        if(in_array($target, ['php://temp', 'php://memory', null], true) === FALSE){
            throw new InvalidArgumentException('The target for the stream can only be "php://temp", "php://memory" or NULL.');
        }
        $this->target = $target;
        if($body === null){
            $body = '';
        }
        if($this->target === null){
            if(!is_scalar($body)){
                throw new InvalidArgumentException("The parameter \$body must be a string.");
            }
            $this->stream = (string)$body;
            $this->seek = $this->size = strlen($this->stream);
            $this->readable = true;
            $this->writable = true;
            $this->seekable = true;
            return $this;
        }
        if(is_string($body)){
            $resource = fopen($this->target, 'rw+');
            fwrite($resource, $body);
            $body = $resource;
        }
        if(is_resource($body)){
            $this->stream = $body;
            $meta = stream_get_meta_data($this->stream);
            $this->seekable = $meta['seekable'] && fseek($this->stream, 0, SEEK_CUR) === 0;
            $this->readable = isset(self::READ_WRITE_HASH['read'][$meta['mode']]);
            $this->writable = isset(self::READ_WRITE_HASH['write'][$meta['mode']]);
            return $this;
        }
        throw new \InvalidArgumentException("The parameter \$body must be a string or a resource.");
    }

    /**
     * @inheritDoc
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
     * @inheritDoc
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
        return is_string($res) ? $this->str2resorce($res) : $res;
    }

    /**
     * @inheritDoc
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
     * @inheritDoc
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
     * @inheritDoc
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
     * @inheritDoc
     */
    public function isSeekable(): bool
    {
        return $this->seekable;
    }

    /**
     * @inheritDoc
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
     * @inheritDoc
     */
    public function rewind()
    {
        $this->seek(0);
    }

    /**
     * @inheritDoc
     */
    public function isWritable(): bool
    {
        return $this->writable;
    }

    /**
     * @inheritDoc
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
        if(is_string($this->stream)){
            if($this->size === null){
                $this->size = strlen($this->stream);
            }
            if($this->seek === 0){
                $this->stream = $string . $this->stream;
            }elseif($this->seek >= $this->size){
                $this->stream .= $string;
            }else{
                $stream = $this->stream;
                $this->stream = substr($stream, 0, $this->seek)
                    . $string
                    . substr($stream, $this->seek);
            }
            $size = strlen($string);
            $this->size += $size;
            return $size;
        }
        $this->size = null;
        if(($result = @fwrite($this->stream, $string)) === FALSE){
            throw new RuntimeException('Unable to write to stream: ' . (error_get_last()['message'] ?? ''));
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function isReadable(): bool
    {
        return $this->readable;
    }

    /**
     * @inheritDoc
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
     * @inheritDoc
     */
    public function getContents(): string
    {
        if(!isset($this->stream)){
            throw new RuntimeException('Stream is detacked');
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
     * @inheritDoc
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

    protected function getUri()
    {
        if($this->uri !== FALSE){
            $this->uri = $this->getMetadata('uri') ?? false;
        }
        return $this->uri;
    }

    /**
     * @param string $string
     * @return resource
     */
    private function str2resorce(string $string)
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $string);
        rewind($stream);
        return $stream;
    }

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
