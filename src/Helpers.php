<?php
/**
 * Helpers.php
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

if (!function_exists('send_request')) {
    /**
     * @param string|\Psr\Http\Message\RequestInterface $method [GET|POST|PUT|PATCH|HEAD|DELETE]
     * @param string|null $url
     * @param array|null $headers
     * @param string|null|\DOMDocument|\SimpleXMLElement|array|object|\Psr\Http\Message\StreamInterface $body
     * @param string|null $version
     * @return \Psr\Http\Message\ResponseInterface
     */
    function send_request($method, ?string $url = null, ?array $headers = [], $body = null, ?string $version = null): \Psr\Http\Message\ResponseInterface
    {
        if ($method instanceof \Psr\Http\Message\RequestInterface) {
            return \InitPHP\HTTP\Facade\Client::sendRequest($method);
        }

        return \InitPHP\HTTP\Facade\Client::fetch($url, [
            'method'        => $method,
            'body'          => $body,
            'headers'       => $headers,
            'version'       => $version,
        ]);
    }
}
