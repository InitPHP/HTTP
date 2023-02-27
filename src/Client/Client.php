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

use \Psr\Http\Message\{RequestInterface, ResponseInterface};
use InitPHP\HTTP\Message\Stream;
use InitPHP\HTTP\Response;
use \InitPHP\HTTP\Client\Exceptions\{ClientException, NetworkException, RequestException};

use function extension_loaded;
use function trim;
use function strtolower;
use function preg_match;
use function explode;
use function ltrim;
use function rtrim;
use function strlen;
use function filter_var;

class Client implements \Psr\Http\Client\ClientInterface
{

    public function __construct()
    {
        if (!extension_loaded('curl')) {
            throw new ClientException('The CURL extension must be installed.');
        }
    }

    /**
     * @inheritDoc
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        try {
            $url = $request->getUri()->__toString();
            if (filter_var($url, \FILTER_VALIDATE_URL)) {
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
            $user_agent = 'InitPHP HTTP PSR-18 Client cURL'
                . (($cVersion = \curl_version()) ? ' ' . $cVersion['version'] : '');
            $options = [
                \CURLOPT_URL                => $url,
                \CURLOPT_RETURNTRANSFER     => true,
                \CURLOPT_ENCODING           => '',
                \CURLOPT_MAXREDIRS          => 10,
                \CURLOPT_TIMEOUT            => 0,
                \CURLOPT_FOLLOWLOCATION     => true,
                \CURLOPT_CUSTOMREQUEST      => $method,
                \CURLOPT_USERAGENT          => $user_agent,
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

}
