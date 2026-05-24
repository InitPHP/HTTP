<?php
/**
 * EmitHeaderException.php
 *
 * This file is part of InitPHP HTTP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\HTTP\Emitter\Exceptions;

/**
 * Raised by {@see \InitPHP\HTTP\Emitter\Emitter} in strict mode when
 * headers have already been sent at the time emit() is called — at that
 * point the HTTP status line and headers cannot be (re)written, so
 * emitting the response would silently break the contract with the
 * client.
 */
class EmitHeaderException extends \RuntimeException
{
}
