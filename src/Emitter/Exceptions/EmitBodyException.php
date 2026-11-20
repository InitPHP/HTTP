<?php
/**
 * EmitBodyException.php
 *
 * This file is part of InitPHP HTTP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://muhammetsafak.com
 */

declare(strict_types=1);

namespace InitPHP\HTTP\Emitter\Exceptions;

/**
 * Raised by {@see \InitPHP\HTTP\Emitter\Emitter} in strict mode when an
 * active output buffer already contains content at the time emit() is
 * called — emitting the response body in that situation would interleave
 * unrelated output with the HTTP body on the wire.
 */
class EmitBodyException extends \RuntimeException
{
}
