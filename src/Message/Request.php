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

namespace InitPHP\HTTP\Message;

use \InitPHP\HTTP\Message\Traits\{MessageTrait, RequestTrait};
use Psr\Http\Message\UriInterface;
use stdClass;
use function in_array;
use function function_exists;
use function array_key_exists;
use function json_decode;
use function json_encode;
use function array_merge;
use function defined;
use function file_get_contents;

class Request implements \InitPHP\HTTP\Message\Interfaces\RequestInterface
{

    use MessageTrait, RequestTrait;

    private array $_parameters = [];

    private object $_objParameters;

    private static Request $requestImmutable;

    public function __construct(string $method, $uri, array $headers = [], $body = null, string $version = '1.1')
    {
        if(!($uri instanceof UriInterface)) {
            $uri = new Uri($uri);
        }
        $this->setUpConstruct($method, $uri, $body, $headers, $version);

        if (!isset($this->_objParameters)) {
            $this->_objParameters = new stdClass();
        }
    }

    public function __isset($name)
    {
        return array_key_exists($name, $this->_parameters);
    }

    public function __set($name, $value)
    {
        $this->_parameters[$name] = $value;
        $this->_objParameters = json_decode(json_encode($this->_parameters));

        return $value;
    }

    public function __get($name)
    {
        return array_key_exists($name, $this->_parameters) ? $this->_objParameters->{$name} : null;
    }

    public static function createFromGlobals(): self
    {
        if (!isset(self::$requestImmutable)) {
            $method = ($_SERVER['REQUEST_METHOD']) ?? ((defined('PHP_SAPI') && PHP_SAPI === 'cli') ? 'CLI' : 'GET');
            $uri = (($_SERVER['HTTPS'] ?? 'off') === 'on' ? 'https' : 'http')
                    . '://'
                    . (($_SERVER['SERVER_NAME']) ?? ($_ENV['SERVER_NAME'] ?? 'localhost'))
                    . (in_array(($_SERVER['SERVER_PORT'] ?? null), [80, 443]) ? ':' . $_SERVER['SERVER_PORT'] : '')
                    . ($_SERVER['REQUEST_URI'] ?? '/');

            $headers = function_exists('apache_request_headers') ? apache_request_headers() : false;
            if (!$headers) {
                $headers = [];
            }

            if (($body = @file_get_contents('php://input')) === FALSE) {
                $body = '';
            }
            self::$requestImmutable = new self($method, $uri, $headers, new \InitPHP\HTTP\Message\Stream($body, null), '1.1');

            if (($rawData = !empty($body) ? json_decode($body, true) : false) === FALSE) {
                $rawData = [];
            }

            self::$requestImmutable->merge($_GET ?? [], $_POST ?? [], $rawData);
        }

        return self::$requestImmutable;
    }


    public function all(): array
    {
        return $this->_parameters;
    }

    public function get(string $name, $default = null)
    {
        return array_key_exists($name, $this->_parameters) ? $this->_parameters[$name] : $default;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->_parameters);
    }

    public function merge(array ...$array): self
    {
        $this->_parameters = array_merge($this->_parameters, ...$array);
        !empty($this->_parameters) && $this->_objParameters = (object)json_decode(json_encode($this->_parameters), false);

        return $this;
    }

    public function sendRequest(): \Psr\Http\Message\ResponseInterface
    {
        if (isset(self::$requestImmutable) && $this === self::$requestImmutable) {
            throw new \RuntimeException('Making requests to itself causes an infinite loop problem.');
        }

        return (new \InitPHP\HTTP\Client\Client())->sendRequest($this);
    }

}
