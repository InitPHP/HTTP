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
use function strtolower;
use function preg_match;
use function explode;
use function ltrim;
use function rtrim;
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

    protected string $userAgent;

    public function __construct()
    {
        if (!extension_loaded('curl')) {
            throw new ClientException('The CURL extension must be installed.');
        }
    }

    public function getUserAgent(): string
    {
        return $this->userAgent ?? 'InitPHP HTTP PSR-18 Client cURL';
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
        if ($request instanceof \InitPHP\HTTP\Message\Request) {
            $requestParameters = $request->all();
            if (!empty($requestParameters) && empty(trim($request->getBody()->getContents()))) {
                $bodyContent = json_encode($requestParameters);
                $request->getBody()->isWritable()
                    ? $request->getBody()->write($bodyContent)
                    : $request->setBody(new Stream($bodyContent, null));
            }
        }

        $options = $this->prepareCurlOptions($request);
        try {
            $curl = \curl_init();
            \curl_setopt_array($curl, $options);
            if (!\curl_errno($curl)) {
                $response['body'] = \curl_exec($curl);
            } else {
                throw new ClientException(\curl_error($curl), (int)\curl_errno($curl));
            }
        } catch (\Throwable $e) {
            throw new NetworkException($request, $e->getMessage(), (int)$e->getCode(), $e->getPrevious());
        } finally {
            \curl_reset($curl);
            \curl_close($curl);
        }

        return new Response($response['status'], $response['headers'], new Stream($response['body'], null), $response['version']);
    }


    private function prepareCurlOptions(RequestInterface $request): array
    {
        try {
            $url = $request->getUri()->__toString();
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                throw new ClientException('URL address is not valid.');
            }
            $version = $request->getProtocolVersion();
            $method = $request->getMethod();
            $headers = $request->getHeaders();
            $body = $request->getBody()->getContents();
        } catch (\Throwable $e) {
            throw new RequestException($request, $e->getMessage(), (int)$e->getCode(), $e->getPrevious());
        }

        try {
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

            if ($method === 'HEAD') {
                $options[\CURLOPT_NOBODY] = true;
            } else {
                if (!empty($body)) {
                    $options[\CURLOPT_POSTFIELDS] = $body;
                }
            }
            if (!empty($headers)) {
                $options[\CURLOPT_HTTPHEADER] = [];
                foreach ($headers as $name => $value) {
                    $options[\CURLOPT_HTTPHEADER][] = $name . ': ' . $value;
                }
            }

            $response = [
                'body'      => '',
                'version'   => $version,
                'status'    => 200,
                'headers'   => [],
            ];

            $options[\CURLOPT_HEADERFUNCTION] = function ($ch, $data) use (&$response) {
                $str = trim($data);
                if (!empty($str)) {
                    $lowercase = strtolower($str);
                    if (preg_match("/http\/([\.0-2]+) ([\d]+).?/i", $lowercase, $matches)) {
                        $response['version'] = $matches[1];
                        $response['status'] = (int)$matches[2];
                    } else {
                        $split = explode(':', $str, 2);
                        $response['headers'][trim($split[0], ' ')] = ltrim(rtrim($split[1], ';'), ' ');
                    }
                }

                return strlen($data);
            };

        } catch (\Throwable $e) {
            throw new ClientException($e->getMessage(), (int)$e->getCode(), $e->getPrevious());
        }

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
        if ($body instanceof StreamInterface) {
            throw new \InvalidArgumentException("\$body is not supported.");
        }
        return new Request($method, $url, $body, $headers, $version);
    }

}
