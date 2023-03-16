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
use Psr\Http\Message\ResponseInterface;

/**
 * @mixin \InitPHP\HTTP\Client\Client
 * @method static \Psr\Http\Message\ResponseInterface sendRequest(\Psr\Http\Message\RequestInterface $request)
 * @method static string getUserAgent()
 * @method static \InitPHP\HTTP\Client\Client setUserAgent(?string $userAgent = null)
 * @method static \InitPHP\HTTP\Client\Client withUserAgent(?string $userAgent = null)
 * @method static ResponseInterface fetch(string $url, array $details = [])
 * @method static ResponseInterface get(string $url, mixed $body = null, array $headers = [], string $version = '1.1')
 * @method static ResponseInterface post(string $url, mixed $body = null, array $headers = [], string $version = '1.1')
 * @method static ResponseInterface patch(string $url, mixed $body = null, array $headers = [], string $version = '1.1')
 * @method static ResponseInterface put(string $url, mixed $body = null, array $headers = [], string $version = '1.1')
 * @method static ResponseInterface delete(string $url, mixed $body = null, array $headers = [], string $version = '1.1')
 * @method static ResponseInterface head(string $url, mixed $body = null, array $headers = [], string $version = '1.1')
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
