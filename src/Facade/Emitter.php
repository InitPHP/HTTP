<?php
/**
 * Emitter.php
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

namespace InitPHP\HTTP\Facade;

use InitPHP\HTTP\Facade\Interfaces\FacadebleInterface;
use InitPHP\HTTP\Facade\Traits\Facadeble;

/**
 * @mixin \InitPHP\HTTP\Emitter\Emitter
 * @method static void emit(\Psr\Http\Message\ResponseInterface $response, ?int $bufferLength = null)
 */
final class Emitter implements FacadebleInterface
{

    use Facadeble;

    private static \InitPHP\HTTP\Emitter\Emitter $instance;

    public static function getInstance(): object
    {
        if (!isset(self::$instance)) {
            self::$instance = new \InitPHP\HTTP\Emitter\Emitter(true);
        }

        return self::$instance;
    }

}
