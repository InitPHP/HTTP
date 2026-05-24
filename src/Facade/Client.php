<?php
/**
 * Client.php
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
use Psr\Http\Message\ResponseInterface;

/**
 * Static facade over a lazily-constructed {@see \InitPHP\HTTP\Client\Client}
 * singleton. Forwards every static call to the underlying instance via
 * {@see Facadable}, so application code can write
 * `Client::post('https://...')` without manually wiring the PSR-18 client.
 *
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
final class Client implements FacadableInterface
{

    use Facadable;

    private static \InitPHP\HTTP\Client\Client $instance;

    /**
     * Return the shared {@see \InitPHP\HTTP\Client\Client} instance,
     * constructing it on first call. Subsequent calls return the exact
     * same object so configuration changes persist across the facade.
     *
     * @return object
     * @throws \InitPHP\HTTP\Client\Exceptions\ClientException When ext-curl is not loaded.
     */
    public static function getInstance(): object
    {
        if (!isset(self::$instance)) {
            self::$instance = new \InitPHP\HTTP\Client\Client();
        }

        return self::$instance;
    }

}
