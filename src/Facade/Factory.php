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

namespace InitPHP\HTTP\Facade;

use InitPHP\HTTP\Facade\Interfaces\FacadableInterface;
use InitPHP\HTTP\Facade\Traits\Facadable;
use \Psr\Http\Message\{RequestInterface,
    ResponseInterface,
    ServerRequestInterface,
    StreamInterface,
    UploadedFileInterface,
    UriInterface};

/**
 * Static facade over a lazily-constructed
 * {@see \InitPHP\HTTP\Factory\Factory} singleton. Lets callers write
 * `Factory::createResponse(200)` without manually instantiating the PSR-17
 * factory bundle (RequestFactory, ResponseFactory, StreamFactory,
 * UriFactory, UploadedFileFactory, ServerRequestFactory).
 *
 * @mixin \InitPHP\HTTP\Factory\Factory
 * @method static RequestInterface createRequest(string $method, $uri)
 * @method static ResponseInterface createResponse(int $code = 200, string $reasonPhrase = '')
 * @method static ServerRequestInterface createServerRequest(string $method, $uri, array $serverParams = [])
 * @method static StreamInterface createStream(string $content = '')
 * @method static StreamInterface createStreamFromFile(string $filename, string $mode = 'r')
 * @method static StreamInterface createStreamFromResource($resource)
 * @method static UploadedFileInterface createUploadedFile(StreamInterface $stream, int $size = null, int $error = \UPLOAD_ERR_OK, string $clientFilename = null, string $clientMediaType = null)
 * @method static UriInterface createUri(string $uri = '')
 */
final class Factory implements FacadableInterface
{

    use Facadable;

    private static \InitPHP\HTTP\Factory\Factory $instance;

    /**
     * Return the shared {@see \InitPHP\HTTP\Factory\Factory} instance,
     * constructing it on first call.
     *
     * @return object
     */
    public static function getInstance(): object
    {
        if (!isset(self::$instance)) {
            self::$instance = new \InitPHP\HTTP\Factory\Factory();
        }

        return self::$instance;
    }

}
