<?php
/**
 * Request.php
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

namespace InitPHP\HTTP;

use InitPHP\HTTP\Message\Interfaces\RequestInterface;

use function in_array;
use function function_exists;
use function array_key_exists;
use function json_decode;
use function json_encode;
use function array_merge;
use function defined;

class Request extends \InitPHP\HTTP\Message\Request implements RequestInterface
{

    private static array $_parameters = [];

    private static object $_objParameters;

    private static Request $_request;

    public function __construct(string $method, $uri, array $headers = [], $body = null, string $version = '1.1')
    {
        parent::__construct($method, $uri, $headers, $body, $version);
    }

    public static function createFromGlobals(): self
    {
        if (!isset(self::$_request)) {
            $method = ($_SERVER['REQUEST_METHOD']) ?? ((defined('PHP_SAPI') && PHP_SAPI === 'cli') ? 'CLI' : 'GET');
            $uri = (($_SERVER['HTTPS'] ?? 'off') === 'on' ? 'https' : 'http')
                    . '://'
                    . (($_SERVER['SERVER_NAME']) ?? ($_ENV['SERVER_NAME'] ?? 'localhost'))
                    . (in_array(($_SERVER['SERVER_PORT'] ?? null), [80, 443]) ? ':' . $_SERVER['SERVER_PORT'] : '')
                    . ($_SERVER['REQUEST_URI'] ?? '/');
            $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
            if ($headers === FALSE) {
                $headers = [];
            }
            $body = @file_get_contents('php://input');
            if ($body === FALSE) {
                $body = '';
            }
            self::$_request = new self($method, $uri, $headers, new \InitPHP\HTTP\Message\Stream($body, null), '1.1');

            if (($rawData = !empty($body) ? json_decode($body, true) : false) === FALSE) {
                $rawData = [];
            }

            self::$_parameters = array_merge($_REQUEST, $rawData);
            self::$_objParameters = json_decode(json_encode(self::$_parameters));
        }

        return self::$_request;
    }

    public function __isset($name)
    {
        return array_key_exists($name, self::$_parameters);
    }

    public function __set($name, $value)
    {
        self::$_parameters[$name] = $value;
        self::$_objParameters = json_decode(json_encode(self::$_parameters));
        return self::$_parameters[$name];
    }

    public function __get($name)
    {
        return array_key_exists($name, self::$_parameters) ? self::$_objParameters->{$name} : null;
    }

    public function getData(): array
    {
        return self::$_parameters;
    }

    public function merge(array ...$array): self
    {
        self::$_parameters = array_merge(self::$_parameters, ...$array);
        self::$_objParameters = json_decode(json_encode(self::$_parameters));

        return $this;
    }

}
