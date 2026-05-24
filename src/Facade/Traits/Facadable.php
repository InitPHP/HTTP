<?php
/**
 * Facadable.php
 *
 * This file is part of InitPHP HTTP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\HTTP\Facade\Traits;

/**
 * Implementation of {@see \InitPHP\HTTP\Facade\Interfaces\FacadableInterface}
 * that forwards every call to the singleton service returned by
 * {@see getInstance()}.
 */
trait Facadable
{
    /**
     * @param string $name
     * @param array<int,mixed> $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return self::getInstance()->{$name}(...$arguments);
    }

    /**
     * @param string $name
     * @param array<int,mixed> $arguments
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        return self::getInstance()->{$name}(...$arguments);
    }
}
