<?php
/**
 * Helpers.php
 *
 * This file is part of InitPHP HTTP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

if (!function_exists('send_request')) {
    /**
     * Convenience wrapper around InitPHP\HTTP\Facade\Client that accepts
     * either a pre-built PSR-7 RequestInterface or a method+URL pair plus
     * loose configuration:
     *
     *   send_request($psr7Request);
     *   send_request('POST', 'https://api.example/x', ['Accept'=>'application/json'], ['k'=>'v']);
     *
     * Body coercion handled at this convenience layer (the underlying
     * PSR-18 Client only accepts string/resource/StreamInterface/null):
     *   - array  -> json_encode($body), Content-Type added if absent.
     *   - object with __toString() -> (string) $body
     *   - object with toArray()    -> json_encode($body->toArray())
     *   - otherwise               -> passed through verbatim.
     *
     * @param  string|\Psr\Http\Message\RequestInterface $method  HTTP method or a fully-formed PSR-7 request.
     * @param  string|null                               $url     Required when \$method is a string.
     * @param  array<string,string|string[]>|null        $headers
     * @param  mixed                                     $body
     * @param  string|null                               $version HTTP protocol version.
     * @throws \InvalidArgumentException When \$method is a string but \$url is null.
     * @throws \Psr\Http\Client\ClientExceptionInterface On transport-level failure.
     */
    function send_request($method, ?string $url = null, ?array $headers = [], $body = null, ?string $version = null): \Psr\Http\Message\ResponseInterface
    {
        if ($method instanceof \Psr\Http\Message\RequestInterface) {
            return \InitPHP\HTTP\Facade\Client::sendRequest($method);
        }
        if ($url === null) {
            throw new \InvalidArgumentException('send_request() requires a URL when the first argument is an HTTP method.');
        }

        $headers = $headers ?? [];

        if (is_array($body)) {
            $body = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!array_key_exists('Content-Type', $headers) && !array_key_exists('content-type', $headers)) {
                $headers['Content-Type'] = 'application/json; charset=utf-8';
            }
        } elseif (is_object($body) && !($body instanceof \Psr\Http\Message\StreamInterface)) {
            if (method_exists($body, '__toString')) {
                $body = (string) $body;
            } elseif (method_exists($body, 'toArray')) {
                $body = json_encode($body->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (!array_key_exists('Content-Type', $headers) && !array_key_exists('content-type', $headers)) {
                    $headers['Content-Type'] = 'application/json; charset=utf-8';
                }
            } else {
                throw new \InvalidArgumentException(
                    'Unsupported body type ' . get_class($body) . '; pass a string, resource, StreamInterface, '
                    . 'array, object with __toString(), or object with toArray().'
                );
            }
        }

        return \InitPHP\HTTP\Facade\Client::fetch($url, [
            'method'  => $method,
            'body'    => $body,
            'headers' => $headers,
            'version' => $version ?? '1.1',
        ]);
    }
}
