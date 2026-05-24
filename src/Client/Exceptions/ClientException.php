<?php
/**
 * ClientException.php
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

/**
 * Base exception for the HTTP client. Implements PSR-18
 * ClientExceptionInterface so callers can catch every transport failure
 * thrown by this client (including the more specific {@see NetworkException}
 * and {@see RequestException}) with a single catch clause.
 */
class ClientException extends \Exception implements \Psr\Http\Client\ClientExceptionInterface
{
}
