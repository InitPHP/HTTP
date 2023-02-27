<?php
/**
 * Emitter.php
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

namespace InitPHP\HTTP\Emitter;

use InitPHP\HTTP\Emitter\Exceptions\EmitBodyException;
use \Psr\Http\Message\{ResponseInterface,
    StreamInterface};

use function assert;
use function is_string;
use function ucfirst;
use function header;
use function sprintf;
use function headers_sent;
use function is_int;
use function ob_get_level;
use function ob_get_length;
use function flush;
use function strlen;
use function preg_match;

class Emitter
{

    protected ?int $bufferLength = null;

    protected bool $strictMode = true;

    public function __construct(bool $strictMode = true)
    {
        $this->strictMode = $strictMode;
    }

    public function emit(ResponseInterface $response, ?int $bufferLength = null): void
    {
        if($bufferLength !== null && $bufferLength > 0){
            $this->bufferLength = $bufferLength;
        }
        $this->assertNoPreviousOutput();
        $this->emitHeaders($response);
        $this->emitStatusLine($response);
        $this->emitBody($response);
    }

    private function emitBody(ResponseInterface $response): void
    {
        if($this->bufferLength === null){
            echo $response->getBody();
            return;
        }
        flush();
        $stream = $response->getBody();
        $range = $this->parseHeaderContentRange($response->getHeaderLine('content-range'));
        if(isset($range['unit']) && $range['unit'] === 'bytes'){
            $this->emitBodyRange($stream, $range['first'], $range['last']);
            return;
        }
        if($stream->isSeekable()){
            $stream->rewind();
        }
        while(!$stream->eof()){
            echo $stream->read($this->bufferLength);
        }
    }

    private function emitBodyRange(StreamInterface $body, int $first, int $last): void
    {
        $length = $last - ($first + 1);
        if($body->isSeekable()){
            $body->seek($first);
        }
        while($length >= $this->bufferLength && !$body->eof()){
            $content = $body->read($this->bufferLength);
            $length -= strlen($content);
            echo $content;
        }
        if($length > 0 && !$body->eof()){
            echo $body->read($length);
        }
    }

    private function assertNoPreviousOutput(): void
    {
        if ($this->strictMode !== TRUE) {
            return;
        }
        $filename = null;
        $line = null;
        if(headers_sent($filename, $line)){
            assert(is_string($filename) && is_int($line));
            throw new EmitBodyException(sprintf('Unable to emit response; headers already sent in %s:%d', $filename, $line));
        }
        if(ob_get_level() > 0 && ob_get_length() > 0){
            throw new EmitBodyException('Output has been emitted previously; cannot emit response');
        }
    }

    private function emitStatusLine(ResponseInterface $response): void
    {
        $reasonPhrase = $response->getReasonPhrase();
        $statusCode = $response->getStatusCode();
        header(sprintf(
            'HTTP/%s %d%s',
            $response->getProtocolVersion(),
            $statusCode, ($reasonPhrase ? ' ' . $reasonPhrase : '')
        ), true, $statusCode);
    }

    private function emitHeaders(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();

        foreach ($response->getHeaders() as $header => $values){
            assert(is_string($header));
            $name = ucfirst($header);
            $first = ($name !== 'Set-Cookie');
            foreach ($values as $value){
                header(sprintf('%s: %s', $name, $value), $first, $statusCode);
                $first = false;
            }
        }
    }

    private function parseHeaderContentRange(string $header): ?array
    {
        if (preg_match('/(?P<unit>[\w]+)\s+(?P<first>\d+)-(?P<last>\d+)\/(?P<length>\d+|\*)/', $header, $matches)) {
            return [
                'unit' => $matches['unit'],
                'first' => (int)$matches['first'],
                'last' => (int)$matches['last'],
                'length' => ($matches['length'] === '*') ? '*' : (int)$matches['length'],
            ];
        }
        return null;
    }

}
