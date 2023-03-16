<?php
/**
 * NetworkException.php
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

namespace InitPHP\HTTP\Client\Exceptions;

use Psr\Http\Message\RequestInterface;

class NetworkException extends ClientException implements \Psr\Http\Client\NetworkExceptionInterface
{

    private RequestInterface $request;

    public function __construct(RequestInterface $request, $message = "", $code = 0, \Throwable $previous = null)
    {
        $this->request = $request;
        parent::__construct($message, $code, $previous);
    }

    /**
     * @inheritDoc
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

}
