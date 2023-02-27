<?php
/**
 * Client.php
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
 * @mixin \InitPHP\HTTP\Client\Client
 * @method static \Psr\Http\Message\ResponseInterface sendRequest(\Psr\Http\Message\RequestInterface $request)
 */
final class Client implements FacadebleInterface
{

    use Facadeble;

    private static \InitPHP\HTTP\Client\Client $instance;

    public static function getInstance(): object
    {
        if (!isset(self::$instance)) {
            self::$instance = new \InitPHP\HTTP\Client\Client();
        }

        return self::$instance;
    }

}
