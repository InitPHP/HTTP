<?php
/**
 * Facadeble.php
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

namespace InitPHP\HTTP\Facade\Traits;

trait Facadeble
{

    public function __call($name, $arguments)
    {
        return self::getInstance()->{$name}(...$arguments);
    }

    public static function __callStatic($name, $arguments)
    {
        return self::getInstance()->{$name}(...$arguments);
    }

}
