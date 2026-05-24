<?php
/**
 * NetworkException.php
 *
 * This file is part of InitPHP HTTP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\HTTP\Client\Exceptions;

use Psr\Http\Message\RequestInterface;

/**
 * Raised when the HTTP client cannot complete a request because of a
 * transport-level failure: DNS resolution, TCP connect, TLS handshake,
 * timeouts, peer reset, etc. Implements PSR-18 NetworkExceptionInterface
 * so the originating request is recoverable via {@see getRequest()}.
 */
class NetworkException extends ClientException implements \Psr\Http\Client\NetworkExceptionInterface
{

    private RequestInterface $request;

    /**
     * @param  RequestInterface $request  The request that failed in flight.
     * @param  string           $message  Human-readable failure reason (typically the cURL error string).
     * @param  int              $code     Transport error code (typically the cURL errno).
     * @param  \Throwable|null  $previous Underlying exception, if any.
     */
    public function __construct(RequestInterface $request, $message = "", $code = 0, \Throwable $previous = null)
    {
        $this->request = $request;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Return the originating PSR-7 request so callers can log, retry or
     * surface the exact URL that failed.
     *
     * @return RequestInterface
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

}
