<?php
/**
 * RequestException.php
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
 * Raised when the HTTP client refuses to marshal a request — for example
 * because the URL is malformed, the body cannot be coerced, or a PSR-7
 * accessor throws while the request is being read into cURL options.
 * Implements PSR-18 RequestExceptionInterface so the offending request is
 * recoverable via {@see getRequest()}.
 */
class RequestException extends ClientException implements \Psr\Http\Client\RequestExceptionInterface
{

    private RequestInterface $request;

    /**
     * @param  RequestInterface $request  The request that could not be sent.
     * @param  string           $message  Human-readable failure reason.
     * @param  int              $code     Numeric error code (typically passed through from the originating exception).
     * @param  \Throwable|null  $previous Underlying exception, if any.
     */
    public function __construct(RequestInterface $request, $message = "", $code = 0, \Throwable $previous = null)
    {
        $this->request = $request;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Return the PSR-7 request that was rejected so callers can log,
     * report or rebuild it.
     *
     * @return RequestInterface
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

}
