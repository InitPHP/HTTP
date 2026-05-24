<?php
/**
 * Factory.php
 *
 * This file is part of InitPHP HTTP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\HTTP\Factory;

use \RuntimeException;
use \InvalidArgumentException;
use \InitPHP\HTTP\Message\{Request,
    Response,
    ServerRequest,
    Stream,
    UploadedFile,
    Uri};
use \Psr\Http\Message\{RequestFactoryInterface,
    RequestInterface,
    ResponseInterface,
    ServerRequestInterface,
    UploadedFileInterface,
    UriFactoryInterface,
    UploadedFileFactoryInterface,
    StreamFactoryInterface,
    ServerRequestFactoryInterface,
    ResponseFactoryInterface,
    StreamInterface,
    UriInterface};

use function in_array;
use function fopen;
use function error_get_last;

/**
 * PSR-17 factory bundle. A single class implements every PSR-17 factory
 * interface (RequestFactory, ResponseFactory, ServerRequestFactory,
 * StreamFactory, UploadedFileFactory, UriFactory), so applications wiring
 * up DI containers only need to register this one type to satisfy all
 * six bindings.
 */
class Factory implements RequestFactoryInterface, UriFactoryInterface, UploadedFileFactoryInterface, StreamFactoryInterface, ServerRequestFactoryInterface, ResponseFactoryInterface
{

    /**
     * Build a fresh PSR-7 Request.
     *
     * @param  string              $method
     * @param  string|UriInterface $uri
     * @return RequestInterface
     * @throws InvalidArgumentException When $uri is a malformed URI string.
     */
    public function createRequest(string $method, $uri): RequestInterface
    {
        return new Request($method, $uri);
    }

    /**
     * Build a fresh PSR-7 Response. An empty $reasonPhrase resolves to
     * the IANA-registered phrase for $code at the Response level.
     *
     * @param  int    $code
     * @param  string $reasonPhrase
     * @return ResponseInterface
     * @throws InvalidArgumentException When $code or the default HTTP version is invalid.
     */
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return new Response($code, [], null, '1.1', ($reasonPhrase === '' ? null : $reasonPhrase));
    }

    /**
     * Build a fresh PSR-7 ServerRequest with the supplied $_SERVER-style
     * snapshot. To bootstrap from real superglobals call
     * {@see \InitPHP\HTTP\Message\ServerRequest::createFromGlobals()} instead.
     *
     * @param  string              $method
     * @param  string|UriInterface $uri
     * @param  array<string,mixed> $serverParams
     * @return ServerRequestInterface
     * @throws InvalidArgumentException When $uri is a malformed URI string.
     */
    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
    {
        return new ServerRequest($method, $uri, [], null, '1.1', $serverParams);
    }

    /**
     * Build a php://temp-backed Stream pre-loaded with $content.
     *
     * @param  string $content
     * @return StreamInterface
     * @throws RuntimeException When php://temp cannot be opened.
     */
    public function createStream(string $content = ''): StreamInterface
    {
        return new Stream($content, 'php://temp');
    }

    /**
     * Open $filename in $mode and wrap the resulting handle in a Stream.
     *
     * @param  string $filename
     * @param  string $mode     One of the fopen() mode prefixes (r, w, a, x, c).
     * @return StreamInterface
     * @throws RuntimeException         When $filename is empty or cannot be opened.
     * @throws InvalidArgumentException When $mode is not a recognised fopen() mode.
     */
    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        if($filename === ''){
            throw new RuntimeException('Path cannot be empty');
        }
        if($mode === '' || in_array($mode[0], ['r', 'w', 'a', 'x', 'c'], true) === FALSE){
            throw new InvalidArgumentException(sprintf('The mode "%s" is invalid.', $mode));
        }

        if(FALSE === $resource = @fopen($filename, $mode)){
            throw new RuntimeException(sprintf('The file "%s" cannot be opened: %s', $filename, error_get_last()['message'] ?? ''));
        }

        return new Stream($resource, 'php://temp');
    }

    /**
     * Wrap an existing PHP resource handle (or pass through a Stream)
     * in a php://temp-backed Stream.
     *
     * @param  resource|StreamInterface $resource
     * @return StreamInterface
     */
    public function createStreamFromResource($resource): StreamInterface
    {
        if($resource instanceof StreamInterface){
            return $resource;
        }

        return new Stream($resource, 'php://temp');
    }

    /**
     * Build a PSR-7 UploadedFile from a stream. When $size is null the
     * factory tries to derive it from the stream itself before construction.
     *
     * @param  StreamInterface $stream
     * @param  int|null        $size
     * @param  int             $error           One of the UPLOAD_ERR_* constants.
     * @param  string|null     $clientFilename
     * @param  string|null     $clientMediaType
     * @return UploadedFileInterface
     * @throws InvalidArgumentException When the stream cannot be wrapped (e.g. unusable handle).
     */
    public function createUploadedFile(StreamInterface $stream, int $size = null, int $error = \UPLOAD_ERR_OK, string $clientFilename = null, string $clientMediaType = null): UploadedFileInterface
    {
        if($size === null){
            $size = $stream->getSize();
        }

        return new UploadedFile($stream, $size, $error, $clientFilename, $clientMediaType);
    }

    /**
     * Build a PSR-7 Uri from a string.
     *
     * @param  string $uri
     * @return UriInterface
     * @throws InvalidArgumentException When parse_url() cannot interpret $uri.
     */
    public function createUri(string $uri = ''): UriInterface
    {
        return new Uri($uri);
    }

}
