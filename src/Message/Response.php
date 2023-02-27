<?php
/**
 * Response.php
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

use \InvalidArgumentException;
use \InitPHP\HTTP\Message\Traits\MessageTrait;
use \InitPHP\HTTP\Message\Interfaces\{ResponseInterface, StreamInterface};

use function in_array;
use function is_numeric;
use function is_resource;
use function is_scalar;
use function is_string;
use function json_encode;

class Response implements ResponseInterface
{

    use MessageTrait;

    protected const PHRASES = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-status',
        208 => 'Already Reported',
        210 => 'Content Different',
        226 => 'IM Used',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Large',
        415 => 'Unsupported Media Type',
        416 => 'Requested range not satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal ServerRequest Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
        505 => 'HTTP Version not supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    ];

    protected int $statusCode;
    protected string $reasonPhrase;


    /**
     * @param int $status
     * @param array $headers
     * @param StreamInterface|string|null|resource $body
     * @param string $version
     * @param string|null $reason
     */
    public function __construct(int $status = 200, array $headers = [], $body = null, string $version = '1.1', ?string $reason = null)
    {
        if(!in_array($version, ['1.0', '1.1', '2.0'], true)){
            throw new \InvalidArgumentException('Supported HTTP versions are; "1.0", "1.1" or "2.0"');
        }
        $this->setStream($body);
        $this->statusCode = $status;
        $this->setHeaders($headers);
        if($reason === null && isset(self::PHRASES[$this->statusCode])){
            $this->reasonPhrase = self::PHRASES[$this->statusCode];
        }else{
            $this->reasonPhrase = $reason ?? '';
        }
        $this->protocol = $version;
    }

    /**
     * @inheritDoc
     */
    public function setStream($body): self
    {
        if($body === null){
            return $this;
        }
        if($body instanceof StreamInterface){
            $this->stream = $body;
            return $this;
        }
        if(is_resource($body)){
            $this->stream = new Stream($body);
            return $this;
        }
        if(is_scalar($body)){
            $this->stream = new Stream((string)$body);
            return $this;
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @inheritDoc
     */
    public function setStatusCode(int $code, string $reasonPhrase = ''): self
    {
        if(!is_numeric($code)){
            throw new InvalidArgumentException('Status code has to be an integer');
        }
        $code = (int)$code;
        if($code < 100 || $code > 599){
            throw new InvalidArgumentException('Status code has to be an integer between 100 and 599. A status code of '.$code.' was given.');
        }
        $this->statusCode = $code;
        if (empty($reasonPhrase) && isset(self::PHRASES[$code])) {
            $reasonPhrase = self::PHRASES[$code];
        }
        $this->reasonPhrase = $reasonPhrase;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withStatus($code, $reasonPhrase = ''): self
    {
        return (clone $this)->setStatusCode($code, $reasonPhrase);
    }

    /**
     * @inheritDoc
     */
    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    public function json(array $data = [], int $status = 200): self
    {
        return $this->addedHeader('Content-Type', 'application/json')
            ->setBody(new Stream(json_encode($data), null))
            ->setStatusCode($status);
    }

    public function redirect($uri, int $status = 302, int $second = 0): self
    {
        if (is_string($uri)) {
            $uri = new Uri($uri);
        }
        if (!($uri instanceof Uri)) {
            throw new InvalidArgumentException('URI is not invalid.');
        }

        $this->setStatusCode($status);

        return $second < 1
            ? $this->addedHeader('Refresh', '0; url=' . $uri->__toString())
            : $this->addedHeader('Location', $uri->__toString());
    }

}
