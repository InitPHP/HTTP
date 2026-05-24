<?php
/**
 * Emitter.php
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

/**
 * Static facade over a lazily-constructed
 * {@see \InitPHP\HTTP\Emitter\Emitter} singleton. The underlying emitter
 * is created with strict mode enabled, so a single `Emitter::emit($response)`
 * call refuses to run when the SAPI has already started writing output.
 *
 * @mixin \InitPHP\HTTP\Emitter\Emitter
 * @method static void emit(\Psr\Http\Message\ResponseInterface $response, ?int $bufferLength = null)
 */
final class Emitter implements FacadableInterface
{

    use Facadable;

    private static \InitPHP\HTTP\Emitter\Emitter $instance;

    /**
     * Return the shared {@see \InitPHP\HTTP\Emitter\Emitter} instance,
     * constructing it on first call with strict mode enabled.
     *
     * @return object
     */
    public static function getInstance(): object
    {
        if (!isset(self::$instance)) {
            self::$instance = new \InitPHP\HTTP\Emitter\Emitter(true);
        }

        return self::$instance;
    }

}
