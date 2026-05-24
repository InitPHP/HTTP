<?php
/**
 * FacadableInterface.php
 *
 * This file is part of InitPHP HTTP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\HTTP\Facade\Interfaces;

/**
 * Contract every static facade in this package implements.
 *
 * A facade is a thin static front-door over a singleton instance of the
 * underlying service class (Client, Emitter, Factory, ...). The instance
 * is created lazily on first access via {@see getInstance()} and reused
 * thereafter; subsequent reads from a facade always return the same
 * service object, which is the intended behaviour for stateless services.
 */
interface FacadableInterface
{
    /**
     * Forward an instance-level call to the singleton service object.
     *
     * @param string $name
     * @param array<int,mixed> $arguments
     * @return mixed
     */
    public function __call($name, $arguments);

    /**
     * Forward a static call to the singleton service object.
     *
     * @param string $name
     * @param array<int,mixed> $arguments
     * @return mixed
     */
    public static function __callStatic($name, $arguments);

    /**
     * Resolve (creating it on first call) the singleton service instance.
     */
    public static function getInstance(): object;
}
