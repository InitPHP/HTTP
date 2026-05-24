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

namespace InitPHP\HTTP\Client;

use \InitPHP\HTTP\Message\{Request, Stream, Response};
use \Psr\Http\Message\{RequestInterface, ResponseInterface, StreamInterface};
use \InitPHP\HTTP\Client\Exceptions\{ClientException, NetworkException, RequestException};

use const CASE_LOWER;
use const FILTER_VALIDATE_URL;

use function extension_loaded;
use function trim;
use function strpos;
use function count;
use function preg_match;
use function explode;
use function implode;
use function strlen;
use function filter_var;
use function array_change_key_case;
use function json_encode;
use function is_string;
use function is_array;
use function is_object;
use function method_exists;
use function class_exists;
use function get_object_vars;

class Client implements \Psr\Http\Client\ClientInterface
{

    protected string $userAgent = 'InitPHP HTTP PSR-18 Client cURL';

    public function __construct()
    {
        if (!extension_loaded('curl')) {
            throw new ClientException('The CURL extension must be installed.');
        }
    }

    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent = null): self
    {
        !empty($userAgent) && $this->userAgent = $userAgent;

        return $this;
    }

    public function withUserAgent(?string $userAgent = null): self
    {
        return (clone $this)->setUserAgent($userAgent);
    }

    public function fetch(string $url, array $details = []): ResponseInterface
    {
        $details = array_change_key_case($details, CASE_LOWER);

        $request = $this->prepareRequest(
            $details['method'] ?? 'GET',
            $url,
            $details['data'] ?? $details['body'] ?? null,
            $details['headers'] ?? [],
            $details['version'] ?? '1.1'
        );

        return $this->sendRequest($request);
    }

    public function get(string $url, $body = null, array $headers = [], string $version = '1.1'): ResponseInterface
    {
        return $this->sendRequest($this->prepareRequest('GET', $url, $body, $headers, $version));
    }

    public function post(string $url, $body = null, array $headers = [], string $version = '1.1'): ResponseInterface
    {
        return $this->sendRequest($this->prepareRequest('POST', $url, $body, $headers, $version));
    }

    public function patch(string $url, $body = null, array $headers = [], string $version = '1.1'): ResponseInterface
    {
        return $this->sendRequest($this->prepareRequest('PATCH', $url, $body, $headers, $version));
    }

    public function put(string $url, $body = null, array $headers = [], string $version = '1.1'): ResponseInterface
    {
        return $this->sendRequest($this->prepareRequest('PUT', $url, $body, $headers, $version));
    }

    public function delete(string $url, $body = null, array $headers = [], string $version = '1.1'): ResponseInterface
    {
        return $this->sendRequest($this->prepareRequest('DELETE', $url, $body, $headers, $version));
    }

    public function head(string $url, $body = null, array $headers = [], string $version = '1.1'): ResponseInterface
    {
        return $this->sendRequest($this->prepareRequest('HEAD', $url, $body, $headers, $version));
    }

    /**
     * @inheritDoc
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        if ($request instanceof Request) {
            $requestParameters = $request->all();
            if (!empty($requestParameters)) {
                $existing = (string) $request->getBody();
                if (trim($existing) === '') {
                    $request = $request->withBody(new Stream(json_encode($requestParameters), null));
                }
            }
        }

        $response = [
            'body'      => '',
            'version'   => $request->getProtocolVersion(),
            'status'    => 200,
            'headers'   => [],
        ];

        $options = $this->prepareCurlOptions($request, $response);

        $curl = \curl_init();
        if ($curl === false) {
            throw new ClientException('Unable to initialize cURL session.');
        }
        try {
            \curl_setopt_array($curl, $options);
            $body = \curl_exec($curl);
            if ($body === false) {
                $errno = \curl_errno($curl);
                $error = \curl_error($curl);
                throw new NetworkException($request, $error !== '' ? $error : 'cURL error', (int) $errno);
            }
            $response['body'] = (string) $body;
        } finally {
            if (\PHP_VERSION_ID < 80500) {
                \curl_close($curl);
            }
        }

        return new Response(
            $response['status'],
            $response['headers'],
            new Stream($response['body'], null),
            $response['version']
        );
    }


    private function prepareCurlOptions(RequestInterface $request, array &$response): array
    {
        try {
            $url = $request->getUri()->__toString();
            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                throw new ClientException('URL address is not valid.');
            }
            $version = $request->getProtocolVersion();
            $method = $request->getMethod();
            $headers = $request->getHeaders();
            $body = (string) $request->getBody();
        } catch (ClientException $e) {
            throw new RequestException($request, $e->getMessage(), (int) $e->getCode(), $e);
        } catch (\Throwable $e) {
            throw new RequestException($request, $e->getMessage(), (int) $e->getCode(), $e);
        }

        $options = [
            \CURLOPT_URL                => $url,
            \CURLOPT_RETURNTRANSFER     => true,
            \CURLOPT_ENCODING           => '',
            \CURLOPT_MAXREDIRS          => 10,
            \CURLOPT_TIMEOUT            => 0,
            \CURLOPT_FOLLOWLOCATION     => true,
            \CURLOPT_CUSTOMREQUEST      => $method,
            \CURLOPT_USERAGENT          => $this->getUserAgent(),
        ];
        switch ($version) {
            case '1.0':
                $options[\CURLOPT_HTTP_VERSION] = \CURL_HTTP_VERSION_1_0;
                break;
            case '2.0':
                $options[\CURLOPT_HTTP_VERSION] = \CURL_HTTP_VERSION_2_0;
                break;
            default:
                $options[\CURLOPT_HTTP_VERSION] = \CURL_HTTP_VERSION_1_1;
        }

        if (\strtoupper($method) === 'HEAD') {
            $options[\CURLOPT_NOBODY] = true;
        } elseif ($body !== '') {
            $options[\CURLOPT_POSTFIELDS] = $body;
        }

        if (!empty($headers)) {
            $flat = [];
            foreach ($headers as $name => $value) {
                $valueStr = is_array($value) ? implode(', ', $value) : (string) $value;
                $flat[] = $name . ': ' . $valueStr;
            }
            $options[\CURLOPT_HTTPHEADER] = $flat;
        }

        $options[\CURLOPT_HEADERFUNCTION] = function ($ch, $data) use (&$response) {
            $str = trim($data);
            if ($str === '') {
                return strlen($data);
            }
            if (preg_match('#^HTTP/(\d(?:\.\d)?)\s+(\d{3})#i', $str, $matches)) {
                $protocol = $matches[1];
                if (strpos($protocol, '.') === false) {
                    $protocol .= '.0';
                }
                $response['version'] = $protocol;
                $response['status']  = (int) $matches[2];
                $response['headers'] = [];
                return strlen($data);
            }
            $split = explode(':', $str, 2);
            if (count($split) === 2) {
                $name  = trim($split[0]);
                $value = trim($split[1]);
                $response['headers'][$name][] = $value;
            }
            return strlen($data);
        };

        return $options;
    }

    private function prepareRequest(string $method, string $url, $body = null, array $headers = [], string $version = '1.1'): RequestInterface
    {
        if ($body === null) {
            $body = new Stream('', null);
        } else if (is_string($body)) {
            $body = new Stream($body, null);
        } else if (is_array($body)) {
            $body = new Stream(json_encode($body), null);
        } else if ((class_exists('DOMDocument')) && ($body instanceof \DOMDocument)) {
            $body = new Stream($body->saveHTML(), null);
        } else if ((class_exists('SimpleXMLElement')) && ($body instanceof \SimpleXMLElement)) {
            $body = new Stream($body->asXML(), null);
        } else if ((is_object($body)) && !($body instanceof StreamInterface)) {
            if (method_exists($body, '__toString')) {
                $body = $body->__toString();
            } else if (method_exists($body, 'toArray')) {
                $body = json_encode($body->toArray());
            } else {
                $body = json_encode(get_object_vars($body));
            }
            $body = new Stream($body, null);
        }
        if (!($body instanceof StreamInterface)) {
            throw new \InvalidArgumentException("\$body is not supported.");
        }
        return new Request($method, $url, $headers, $body, $version);
    }

}
