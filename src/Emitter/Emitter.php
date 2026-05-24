<?php
/**
 * Emitter.php
 *
 * This file is part of InitPHP HTTP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\HTTP\Emitter;

use InitPHP\HTTP\Emitter\Exceptions\EmitBodyException;
use InitPHP\HTTP\Emitter\Exceptions\EmitHeaderException;
use \Psr\Http\Message\{ResponseInterface, StreamInterface};

use function assert;
use function is_string;
use function ucwords;
use function strtolower;
use function header;
use function sprintf;
use function headers_sent;
use function is_int;
use function ob_get_level;
use function ob_get_length;
use function flush;
use function strlen;
use function preg_match;

/**
 * Server-side emitter for PSR-7 responses. Writes the status line, the
 * normalised headers and the body to the SAPI / PHP-FPM output stream,
 * with optional chunked emission and HTTP Range support. Strict mode (on
 * by default) refuses to emit when headers have already been sent or when
 * the output buffer is dirty, so partial responses never reach the wire.
 */
class Emitter
{

    protected bool $strictMode = true;

    /**
     * @param  bool $strictMode When true, refuse to emit if headers have already been
     *                          sent or output has already been written.
     */
    public function __construct(bool $strictMode = true)
    {
        $this->strictMode = $strictMode;
    }

    /**
     * Emit the response. Writes the HTTP status line, every header, and
     * the body to the output stream. When $bufferLength is a positive
     * integer the body is streamed in chunks of that size (and Content-Range
     * is honoured); otherwise the body is echoed in one shot via the
     * Stream's __toString().
     *
     * @param  ResponseInterface $response
     * @param  int|null          $bufferLength Optional chunk size for streamed emission.
     * @return void
     * @throws EmitHeaderException When strict mode is on and headers have already been sent.
     * @throws EmitBodyException   When strict mode is on and the output buffer already contains content.
     */
    public function emit(ResponseInterface $response, ?int $bufferLength = null): void
    {
        $this->assertNoPreviousOutput();
        $this->emitStatusLine($response);
        $this->emitHeaders($response);
        if($bufferLength !== null && $bufferLength > 0){
            $this->emitBody($response, $bufferLength);
            return;
        }
        echo $response->getBody();
    }

    /**
     * Stream the response body to the output in chunks of $bufferLength
     * bytes. When the response advertises a `Content-Range: bytes ...`
     * header, only the requested byte range is emitted.
     *
     * @param  ResponseInterface $response
     * @param  int               $bufferLength
     * @return void
     */
    private function emitBody(ResponseInterface $response, int $bufferLength): void
    {
        flush();
        $stream = $response->getBody();
        $range = $this->parseHeaderContentRange($response->getHeaderLine('content-range'));
        if(isset($range['unit']) && $range['unit'] === 'bytes'){
            $this->emitBodyRange($stream, (int)$range['first'], (int)$range['last'], $bufferLength);
            return;
        }
        if($stream->isSeekable()){
            $stream->rewind();
        }
        while(!$stream->eof()){
            echo $stream->read($bufferLength);
        }
    }

    /**
     * Stream a contiguous byte range from $body to the output. The body
     * is seeked to $first; bytes are read in $bufferLength chunks until
     * exactly $last - $first + 1 bytes have been written.
     *
     * @param  StreamInterface $body
     * @param  int             $first
     * @param  int             $last
     * @param  int             $bufferLength
     * @return void
     */
    private function emitBodyRange(StreamInterface $body, int $first, int $last, int $bufferLength): void
    {
        $length = $last - $first + 1;
        if($body->isSeekable()){
            $body->seek($first);
        }
        while($length >= $bufferLength && !$body->eof()){
            $content = $body->read($bufferLength);
            $length -= strlen($content);
            echo $content;
        }
        if($length > 0 && !$body->eof()){
            echo $body->read($length);
        }
    }

    /**
     * Verify that nothing has been emitted to the client yet. Header-vs-body
     * dirtiness is reported as two different exception types so callers can
     * tell at a glance which contract has been violated. No-op when strict
     * mode is disabled.
     *
     * @return void
     * @throws EmitHeaderException When headers have already been sent.
     * @throws EmitBodyException   When the output buffer already contains content.
     */
    private function assertNoPreviousOutput(): void
    {
        if ($this->strictMode !== true) {
            return;
        }
        $filename = null;
        $line = null;
        if (headers_sent($filename, $line)) {
            assert(is_string($filename) && is_int($line));
            throw new EmitHeaderException(sprintf(
                'Unable to emit response; headers already sent in %s:%d',
                $filename,
                $line
            ));
        }
        if (ob_get_level() > 0 && ob_get_length() > 0) {
            throw new EmitBodyException('Output has been emitted previously; cannot emit response.');
        }
    }

    /**
     * Send the HTTP status line for $response via header(), including
     * the protocol version, status code and reason phrase.
     *
     * @param  ResponseInterface $response
     * @return void
     */
    private function emitStatusLine(ResponseInterface $response): void
    {
        $reasonPhrase = $response->getReasonPhrase();
        $statusCode = $response->getStatusCode();
        header(sprintf(
            'HTTP/%s %d%s',
            $response->getProtocolVersion(),
            $statusCode,
            $reasonPhrase !== '' ? ' ' . $reasonPhrase : ''
        ), true, $statusCode);
    }

    /**
     * Send every response header to the client. Header names are
     * canonicalised to Word-Case; Set-Cookie is emitted with `replace=false`
     * so multiple cookies stack instead of overwriting one another.
     *
     * @param  ResponseInterface $response
     * @return void
     */
    private function emitHeaders(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();

        foreach ($response->getHeaders() as $header => $values){
            assert(is_string($header));
            $name = ucwords(strtolower($header), '-');
            $first = (strtolower($name) !== 'set-cookie');
            foreach ($values as $value){
                header(sprintf('%s: %s', $name, $value), $first, $statusCode);
                $first = false;
            }
        }
    }

    /**
     * Parse an RFC 7233 `Content-Range` header value into its constituent
     * parts (unit, first byte, last byte, total length). Returns null
     * when the header does not match the expected pattern.
     *
     * @param  string $header Raw header value.
     * @return array{unit:string,first:int,last:int,length:int|string}|null
     */
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
