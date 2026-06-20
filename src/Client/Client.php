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

namespace InitPHP\HTTP\Client;

use \InitPHP\HTTP\Message\{Request, Stream, Response};
use \Psr\Http\Message\{RequestInterface, ResponseInterface, StreamInterface};
use \InitPHP\HTTP\Client\Exceptions\{ClientException, NetworkException, RequestException};
use \InitPHP\HTTP\Client\Retry\{Backoff, RetryAfter, RetryPolicy, Sleeper, SleeperInterface};
use \Psr\Http\Client\NetworkExceptionInterface;

use const CASE_LOWER;
use const FILTER_VALIDATE_URL;

use function array_change_key_case;
use function count;
use function explode;
use function extension_loaded;
use function filter_var;
use function implode;
use function in_array;
use function is_array;
use function is_resource;
use function is_string;
use function max;
use function preg_match;
use function strlen;
use function strpos;
use function trim;

/**
 * PSR-18 ClientInterface implementation backed by ext-curl. Wraps cURL
 * with sensible production defaults (timeout, connect timeout, redirect
 * cap, user agent), exposes per-instance overrides for CURLOPT_* values,
 * and ships a small set of convenience methods (get/post/put/...) on top
 * of the standard sendRequest() entry point.
 */
class Client implements \Psr\Http\Client\ClientInterface
{

    protected string $userAgent = 'InitPHP HTTP PSR-18 Client cURL';

    /**
     * cURL options merged on top of the defaults computed by
     * {@see Client::prepareCurlOptions()}. Use these to inject SSL roots,
     * proxies, custom timeouts, etc., without subclassing.
     *
     * @var array<int,mixed>
     */
    protected array $curlOptions = [];

    /**
     * Total request timeout in seconds (CURLOPT_TIMEOUT). Defaults to 30s
     * instead of cURL's 0 (no timeout) — a hard requirement for any
     * production-grade HTTP client.
     */
    protected int $timeout = 30;

    /**
     * Connection establishment timeout (CURLOPT_CONNECTTIMEOUT) in seconds.
     */
    protected int $connectTimeout = 10;

    /**
     * Whether the client should transparently follow 3xx redirects.
     */
    protected bool $followRedirects = true;

    /**
     * Upper bound on followed redirects when {@see Client::$followRedirects}
     * is enabled.
     */
    protected int $maxRedirects = 10;

    /**
     * Opt-in retry policy. When null (the default), {@see Client::sendRequest()}
     * makes exactly one transport attempt and returns/throws as before — fully
     * backward-compatible. Attaching a policy turns on retry-with-backoff.
     */
    protected ?RetryPolicy $retryPolicy = null;

    /**
     * Pacing strategy used to wait between retry attempts. Injectable so the
     * retry loop can be exercised without real wall-clock delays in tests.
     */
    protected SleeperInterface $sleeper;

    /**
     * Optional jitter randomness source (a callable returning a float in
     * [0, 1)) forwarded to {@see Backoff}. null defers to Backoff's default.
     *
     * @var (callable(): float)|null
     */
    protected $jitterRandomizer = null;

    /**
     * Construct a client and assert ext-curl is loaded; the cURL extension
     * is the only transport this client speaks.
     *
     * @throws ClientException When ext-curl is not loaded.
     */
    public function __construct()
    {
        if (!extension_loaded('curl')) {
            throw new ClientException('The CURL extension must be installed.');
        }
        $this->sleeper = new Sleeper();
    }

    /**
     * Return the user agent string sent on every outbound request.
     *
     * @return string
     */
    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    /**
     * Replace the user agent (in-place). null or empty values are ignored
     * so callers cannot accidentally send a blank UA header.
     *
     * @param  string|null $userAgent
     * @return $this
     */
    public function setUserAgent(?string $userAgent = null): self
    {
        if ($userAgent !== null && $userAgent !== '') {
            $this->userAgent = $userAgent;
        }

        return $this;
    }

    /**
     * Return a clone of the client with the user agent replaced.
     *
     * @param  string|null $userAgent
     * @return $this
     */
    public function withUserAgent(?string $userAgent = null): self
    {
        return (clone $this)->setUserAgent($userAgent);
    }

    /**
     * Replace the per-instance cURL option overrides. Keys must be one of
     * the CURLOPT_* constants; values are forwarded verbatim to
     * curl_setopt_array().
     *
     * @param  array<int,mixed> $options
     * @return $this
     */
    public function setCurlOptions(array $options): self
    {
        $this->curlOptions = $options;

        return $this;
    }

    /**
     * Return a clone of the client with the cURL option overrides replaced.
     *
     * @param  array<int,mixed> $options
     * @return $this
     */
    public function withCurlOptions(array $options): self
    {
        return (clone $this)->setCurlOptions($options);
    }

    /**
     * Return the current cURL option override map.
     *
     * @return array<int,mixed>
     */
    public function getCurlOptions(): array
    {
        return $this->curlOptions;
    }

    /**
     * Replace the total request timeout (CURLOPT_TIMEOUT, in seconds).
     * Negative inputs are clamped to 0.
     *
     * @param  int $seconds
     * @return $this
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = max(0, $seconds);

        return $this;
    }

    /**
     * Return a clone of the client with the request timeout replaced.
     *
     * @param  int $seconds
     * @return $this
     */
    public function withTimeout(int $seconds): self
    {
        return (clone $this)->setTimeout($seconds);
    }

    /**
     * Replace the connect timeout (CURLOPT_CONNECTTIMEOUT, in seconds).
     * Negative inputs are clamped to 0.
     *
     * @param  int $seconds
     * @return $this
     */
    public function setConnectTimeout(int $seconds): self
    {
        $this->connectTimeout = max(0, $seconds);

        return $this;
    }

    /**
     * Return a clone of the client with the connect timeout replaced.
     *
     * @param  int $seconds
     * @return $this
     */
    public function withConnectTimeout(int $seconds): self
    {
        return (clone $this)->setConnectTimeout($seconds);
    }

    /**
     * Configure 3xx redirect handling (in-place). $follow toggles
     * CURLOPT_FOLLOWLOCATION; $max caps CURLOPT_MAXREDIRS and is clamped
     * to zero or greater.
     *
     * @param  bool $follow
     * @param  int  $max
     * @return $this
     */
    public function setFollowRedirects(bool $follow, int $max = 10): self
    {
        $this->followRedirects = $follow;
        $this->maxRedirects    = max(0, $max);

        return $this;
    }

    /**
     * Return a clone of the client with the redirect-handling settings
     * replaced.
     *
     * @param  bool $follow
     * @param  int  $max
     * @return $this
     */
    public function withFollowRedirects(bool $follow, int $max = 10): self
    {
        return (clone $this)->setFollowRedirects($follow, $max);
    }

    /**
     * Attach (or clear) the retry-with-backoff policy in place. Passing null
     * restores the default single-attempt behaviour.
     *
     * @param  RetryPolicy|null $policy
     * @return $this
     */
    public function setRetryPolicy(?RetryPolicy $policy): self
    {
        $this->retryPolicy = $policy;

        return $this;
    }

    /**
     * Return a clone of the client with the retry policy replaced (or cleared).
     *
     * @param  RetryPolicy|null $policy
     * @return $this
     */
    public function withRetryPolicy(?RetryPolicy $policy): self
    {
        return (clone $this)->setRetryPolicy($policy);
    }

    /**
     * Return the active retry policy, or null when retries are disabled.
     *
     * @return RetryPolicy|null
     */
    public function getRetryPolicy(): ?RetryPolicy
    {
        return $this->retryPolicy;
    }

    /**
     * Replace the inter-attempt pacing strategy (in place). Primarily a test
     * seam: inject a no-op sleeper to exercise the retry loop instantly.
     *
     * @param  SleeperInterface $sleeper
     * @return $this
     */
    public function setSleeper(SleeperInterface $sleeper): self
    {
        $this->sleeper = $sleeper;

        return $this;
    }

    /**
     * Return a clone of the client with the pacing strategy replaced.
     *
     * @param  SleeperInterface $sleeper
     * @return $this
     */
    public function withSleeper(SleeperInterface $sleeper): self
    {
        return (clone $this)->setSleeper($sleeper);
    }

    /**
     * Replace the jitter randomness source (in place) forwarded to the backoff
     * calculator. Pass null to defer to the default mt_rand()-based source.
     *
     * @param  (callable(): float)|null $randomizer Returns a float in [0, 1).
     * @return $this
     */
    public function setJitterRandomizer(?callable $randomizer): self
    {
        $this->jitterRandomizer = $randomizer;

        return $this;
    }

    /**
     * Return a clone of the client with the jitter randomness source replaced.
     *
     * @param  (callable(): float)|null $randomizer
     * @return $this
     */
    public function withJitterRandomizer(?callable $randomizer): self
    {
        return (clone $this)->setJitterRandomizer($randomizer);
    }

    /**
     * Dispatch a request specified as a $url + loose options array. Keys
     * are matched case-insensitively and may include `method`, `data`,
     * `body`, `headers` and `version`. Returns the PSR-7 response.
     *
     * @param  string                                                $url
     * @param  array<string,mixed>                                   $details
     * @return ResponseInterface
     * @throws ClientException        On invalid configuration (e.g. malformed URL).
     * @throws RequestException       When the request cannot be prepared for cURL.
     * @throws NetworkException       On transport-level failure (DNS, TCP, TLS, ...).
     * @throws \InvalidArgumentException When `data`/`body` is a type the underlying client cannot accept.
     */
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

    /**
     * Dispatch a GET request and return the response.
     *
     * @param  string                                          $url
     * @param  string|resource|StreamInterface|null            $body
     * @param  array<string,string|string[]>                   $headers
     * @param  string                                          $version
     * @return ResponseInterface
     * @throws ClientException|RequestException|NetworkException On configuration or transport failure.
     * @throws \InvalidArgumentException                         When $body is not a supported shape.
     */
    public function get(string $url, $body = null, array $headers = [], string $version = '1.1'): ResponseInterface
    {
        return $this->sendRequest($this->prepareRequest('GET', $url, $body, $headers, $version));
    }

    /**
     * Dispatch a POST request and return the response.
     *
     * @param  string                                          $url
     * @param  string|resource|StreamInterface|null            $body
     * @param  array<string,string|string[]>                   $headers
     * @param  string                                          $version
     * @return ResponseInterface
     * @throws ClientException|RequestException|NetworkException On configuration or transport failure.
     * @throws \InvalidArgumentException                         When $body is not a supported shape.
     */
    public function post(string $url, $body = null, array $headers = [], string $version = '1.1'): ResponseInterface
    {
        return $this->sendRequest($this->prepareRequest('POST', $url, $body, $headers, $version));
    }

    /**
     * Dispatch a PATCH request and return the response.
     *
     * @param  string                                          $url
     * @param  string|resource|StreamInterface|null            $body
     * @param  array<string,string|string[]>                   $headers
     * @param  string                                          $version
     * @return ResponseInterface
     * @throws ClientException|RequestException|NetworkException On configuration or transport failure.
     * @throws \InvalidArgumentException                         When $body is not a supported shape.
     */
    public function patch(string $url, $body = null, array $headers = [], string $version = '1.1'): ResponseInterface
    {
        return $this->sendRequest($this->prepareRequest('PATCH', $url, $body, $headers, $version));
    }

    /**
     * Dispatch a PUT request and return the response.
     *
     * @param  string                                          $url
     * @param  string|resource|StreamInterface|null            $body
     * @param  array<string,string|string[]>                   $headers
     * @param  string                                          $version
     * @return ResponseInterface
     * @throws ClientException|RequestException|NetworkException On configuration or transport failure.
     * @throws \InvalidArgumentException                         When $body is not a supported shape.
     */
    public function put(string $url, $body = null, array $headers = [], string $version = '1.1'): ResponseInterface
    {
        return $this->sendRequest($this->prepareRequest('PUT', $url, $body, $headers, $version));
    }

    /**
     * Dispatch a DELETE request and return the response.
     *
     * @param  string                                          $url
     * @param  string|resource|StreamInterface|null            $body
     * @param  array<string,string|string[]>                   $headers
     * @param  string                                          $version
     * @return ResponseInterface
     * @throws ClientException|RequestException|NetworkException On configuration or transport failure.
     * @throws \InvalidArgumentException                         When $body is not a supported shape.
     */
    public function delete(string $url, $body = null, array $headers = [], string $version = '1.1'): ResponseInterface
    {
        return $this->sendRequest($this->prepareRequest('DELETE', $url, $body, $headers, $version));
    }

    /**
     * Dispatch a HEAD request and return the response.
     *
     * @param  string                                          $url
     * @param  string|resource|StreamInterface|null            $body
     * @param  array<string,string|string[]>                   $headers
     * @param  string                                          $version
     * @return ResponseInterface
     * @throws ClientException|RequestException|NetworkException On configuration or transport failure.
     * @throws \InvalidArgumentException                         When $body is not a supported shape.
     */
    public function head(string $url, $body = null, array $headers = [], string $version = '1.1'): ResponseInterface
    {
        return $this->sendRequest($this->prepareRequest('HEAD', $url, $body, $headers, $version));
    }

    /**
     * Execute the supplied PSR-7 request and return the PSR-7 response.
     *
     * When no {@see RetryPolicy} is attached (the default), this performs a
     * single transport attempt — behaviour identical to previous releases. When
     * a policy is attached, transient failures (retryable status codes and, if
     * enabled, transport exceptions) are retried up to the policy's
     * max-attempts cap, spaced by exponential backoff with jitter. A
     * `Retry-After` header on a retryable response overrides the computed delay
     * when the policy opts in. PSR-18 semantics are preserved: a non-retryable
     * 4xx/5xx response is returned, never thrown.
     *
     * @param  RequestInterface $request
     * @return ResponseInterface
     * @throws ClientException  When cURL cannot be initialised at all.
     * @throws RequestException When the request itself cannot be marshalled (invalid URL, body coercion failure).
     * @throws NetworkException When cURL reports a transport-level failure and either retries are disabled or the cap is reached.
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $policy = $this->retryPolicy;
        if ($policy === null) {
            return $this->transport($request);
        }

        $backoff = new Backoff($policy, $this->jitterRandomizer);
        $attempt = 0;

        while (true) {
            $attempt++;
            try {
                $response = $this->transport($request);
            } catch (NetworkExceptionInterface $e) {
                // Transport-level failure (DNS, TCP, TLS, timeout). Retry only
                // when the policy permits it and the attempt cap is not reached;
                // otherwise rethrow so the caller sees the original exception.
                if (!$policy->isRetryOnException() || !$policy->shouldRetry($attempt)) {
                    throw $e;
                }
                $this->sleeper->sleep($backoff->delayFor($attempt));
                continue;
            }

            // A successful transport call with a non-retryable status — or one
            // that has exhausted the attempt budget — is the final answer.
            if (!$policy->isRetryableStatus($response->getStatusCode())
                || !$policy->shouldRetry($attempt)) {
                return $response;
            }

            $this->sleeper->sleep($this->retryDelay($policy, $backoff, $response, $attempt));
        }
    }

    /**
     * Resolve the delay before the next retry for a retryable *response*:
     * honour a parseable `Retry-After` header when the policy opts in,
     * otherwise fall back to the jittered exponential backoff for this attempt.
     *
     * @param  RetryPolicy       $policy
     * @param  Backoff           $backoff
     * @param  ResponseInterface $response
     * @param  int               $attempt 1-based attempt index just completed.
     * @return float Delay in seconds.
     */
    private function retryDelay(RetryPolicy $policy, Backoff $backoff, ResponseInterface $response, int $attempt): float
    {
        if ($policy->isRespectRetryAfter()) {
            $retryAfter = RetryAfter::fromResponse($response);
            if ($retryAfter !== null) {
                return $retryAfter;
            }
        }

        return $backoff->delayFor($attempt);
    }

    /**
     * Perform a single cURL transport attempt for $request and return the
     * PSR-7 response. This is the retry-free primitive that
     * {@see Client::sendRequest()} orchestrates; subclasses and tests can
     * override it to simulate transport behaviour without touching the network.
     *
     * The response body is wrapped in a php://temp-backed Stream so large
     * payloads spill to disk (cURL's 2 MiB threshold) instead of pinning the
     * full response into the process's resident memory.
     *
     * @param  RequestInterface $request
     * @return ResponseInterface
     * @throws ClientException  When cURL cannot be initialised at all.
     * @throws RequestException When the request itself cannot be marshalled (invalid URL, body coercion failure).
     * @throws NetworkException When cURL reports a transport-level failure (DNS, TCP, TLS, timeout, ...).
     */
    protected function transport(RequestInterface $request): ResponseInterface
    {
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
            // Wrap the response body in a php://temp-backed Stream so large
            // payloads spill to disk (default 2 MiB threshold) instead of
            // pinning the entire body into PHP's process memory as a string.
            new Stream($response['body'], 'php://temp'),
            $response['version']
        );
    }


    /**
     * Build the CURLOPT_* option map for $request, also wiring a header
     * callback that captures the response status, version and headers
     * into the supplied $response accumulator (by reference).
     *
     * @param  RequestInterface       $request
     * @param  array<string,mixed>    $response Mutated in place by the header callback.
     * @return array<int,mixed>
     * @throws RequestException When the request URL fails FILTER_VALIDATE_URL, or any accessor on $request throws.
     */
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

        $methodUpper = \strtoupper($method);
        $options = [
            \CURLOPT_URL                => $url,
            \CURLOPT_RETURNTRANSFER     => true,
            \CURLOPT_ENCODING           => '',
            \CURLOPT_MAXREDIRS          => $this->maxRedirects,
            \CURLOPT_TIMEOUT            => $this->timeout,
            \CURLOPT_CONNECTTIMEOUT     => $this->connectTimeout,
            \CURLOPT_FOLLOWLOCATION     => $this->followRedirects,
            \CURLOPT_CUSTOMREQUEST      => $method,
            \CURLOPT_USERAGENT          => $this->getUserAgent(),
        ];
        switch ($version) {
            case '1.0':
                $options[\CURLOPT_HTTP_VERSION] = \CURL_HTTP_VERSION_1_0;
                break;
            case '2':
            case '2.0':
                $options[\CURLOPT_HTTP_VERSION] = \CURL_HTTP_VERSION_2_0;
                break;
            default:
                $options[\CURLOPT_HTTP_VERSION] = \CURL_HTTP_VERSION_1_1;
        }

        if ($methodUpper === 'HEAD') {
            $options[\CURLOPT_NOBODY] = true;
        } elseif (\in_array($methodUpper, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            // POSTFIELDS must be set for body-bearing methods even when the
            // body is empty, otherwise cURL silently downgrades the request
            // to a body-less call and CUSTOMREQUEST becomes the only signal.
            $options[\CURLOPT_POSTFIELDS] = $body;
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

        // Apply user-supplied option overrides last so they can override the
        // defaults computed above (timeouts, redirects, headers, ...).
        foreach ($this->curlOptions as $optKey => $optValue) {
            $options[$optKey] = $optValue;
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

    /**
     * Normalise the body argument accepted by the high-level helpers
     * (`fetch/get/post/...`) into a PSR-7 StreamInterface. Only stream-shaped
     * inputs are accepted; structured serialisation (JSON, XML, form-encoded)
     * is the caller's responsibility and must be performed before reaching
     * the HTTP layer.
     *
     * @param  string                                $method
     * @param  string                                $url
     * @param  string|resource|StreamInterface|null  $body
     * @param  array<string,string|string[]>         $headers
     * @param  string                                $version
     * @return RequestInterface
     * @throws \InvalidArgumentException When $body is not a string, resource, StreamInterface or null.
     */
    private function prepareRequest(string $method, string $url, $body = null, array $headers = [], string $version = '1.1'): RequestInterface
    {
        if ($body === null) {
            $stream = new Stream('', null);
        } elseif ($body instanceof StreamInterface) {
            $stream = $body;
        } elseif (is_string($body)) {
            $stream = new Stream($body, null);
        } elseif (is_resource($body)) {
            $stream = new Stream($body);
        } else {
            throw new \InvalidArgumentException(
                'Request body must be a string, resource, StreamInterface, or null. '
                . 'Encode structured payloads (arrays, DOM, objects) before passing them in.'
            );
        }

        return new Request($method, $url, $headers, $stream, $version);
    }

}
