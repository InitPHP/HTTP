<?php
/**
 * Factory.php
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

namespace InitPHP\HTTP\Factory;

use \RuntimeException;
use \InvalidArgumentException;
use \InitPHP\HTTP\{Request, Response};
use \InitPHP\HTTP\Message\{ServerRequest,
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

class Factory implements RequestFactoryInterface, UriFactoryInterface, UploadedFileFactoryInterface, StreamFactoryInterface, ServerRequestFactoryInterface, ResponseFactoryInterface
{

    /**
     * @inheritDoc
     */
    public function createRequest(string $method, $uri): RequestInterface
    {
        return new Request($method, $uri);
    }

    /**
     * @inheritDoc
     */
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return new Response($code, [], null, '1.1', ($reasonPhrase === '' ? null : $reasonPhrase));
    }

    /**
     * @inheritDoc
     */
    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
    {
        return new ServerRequest($method, $uri, [], null, '1.1', $serverParams);
    }

    /**
     * @inheritDoc
     */
    public function createStream(string $content = ''): StreamInterface
    {
        return new Stream($content, 'php://temp');
    }

    /**
     * @inheritDoc
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
     * @inheritDoc
     */
    public function createStreamFromResource($resource): StreamInterface
    {
        if($resource instanceof StreamInterface){
            return $resource;
        }

        return new Stream($resource, 'php://temp');
    }

    /**
     * @inheritDoc
     */
    public function createUploadedFile(StreamInterface $stream, int $size = null, int $error = \UPLOAD_ERR_OK, string $clientFilename = null, string $clientMediaType = null): UploadedFileInterface
    {
        if($size === null){
            $size = $stream->getSize();
        }

        return new UploadedFile($stream, $size, $error, $clientFilename, $clientMediaType);
    }

    /**
     * @inheritDoc
     */
    public function createUri(string $uri = ''): UriInterface
    {
        return new Uri($uri);
    }

}
