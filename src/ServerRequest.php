<?php
/**
 * ServerRequest.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    http://initphp.github.com/license.txt  MIT
 * @version    1.0
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\HTTP;

use Psr\Http\Message\{UploadedFileInterface, UriInterface, ServerRequestInterface};

use function is_array;
use function is_object;
use function array_keys;

class ServerRequest implements ServerRequestInterface
{
    use MessageTrait, RequestTrait;

    protected array $serverParams = [];
    protected array $cookieParams = [];
    protected array $queryParams = [];
    /** @var null|object|array */
    protected $parsedBody = null;
    protected array $attributes = [];

    /** @var UploadedFileInterface[] */
    protected array $uploadedFiles = [];

    public function __construct(string $method, $uri, array $headers = [], $body = null, string $version = '1.1', array $serverParams = [])
    {
        $this->serverParams = $serverParams;
        if(!($uri instanceof UriInterface)){
            $uri = new Uri($uri);
        }
        $this->setUpConstruct($method, $uri, $body, $headers, $version);
    }

    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    public function withCookieParams(array $cookies): ServerRequest
    {
        $clone = clone $this;
        $clone->cookieParams = $cookies;
        return $clone;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function withQueryParams(array $query): ServerRequest
    {
        $clone = clone $this;
        $clone->queryParams = $query;
        return $clone;
    }

    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    public function withUploadedFiles(array $uploadedFiles): ServerRequest
    {
        $clone = clone $this;
        $clone->uploadedFiles = $this->normalizeFiles($uploadedFiles);
        return $clone;
    }

    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    public function withParsedBody($data): ServerRequest
    {
        if(!is_array($data) && !is_object($data) && $data !== null){
            throw new \InvalidArgumentException('First parameter to withParsedBody MUST be object, array or null');
        }
        $clone = clone $this;
        $clone->parsedBody = $data;
        return $clone;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute($name, $default = null)
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute($name, $value): ServerRequest
    {
        $clone = clone $this;
        $clone->attributes[$name] = $value;
        return $clone;
    }

    public function withoutAttribute($name): ServerRequest
    {
        if(!isset($this->attributes[$name])){
            return $this;
        }
        $clone = clone $this;
        unset($clone->attributes[$name]);
        return $clone;
    }

    /**
     * @param array $uploadedFiles
     * @return UploadedFileInterface[]
     */
    public function normalizeFiles(array $files): array
    {
        $normalized = [];
        foreach ($files as $key => $value) {
            if ($value instanceof UploadedFileInterface) {
                $normalized[$key] = $value;
                continue;
            }
            if(!isset($value['tmp_name'])){
                throw new \InvalidArgumentException('Invalid value in files specification');
            }
            if(is_array($value['tmp_name'])){
                $normalized[$key] = [];
                foreach (array_keys($value['tmp_name']) as $fileId) {
                    $normalized[$key][$fileId] = new UploadedFile(
                        $value['tmp_name'][$fileId],
                        (int)$value['size'][$fileId],
                        (int)$value['error'][$fileId],
                        $value['name'][$fileId],
                        $value['type'][$fileId]
                    );
                }
            }else{
                $normalized[$key] = new UploadedFile(
                    $value['tmp_name'],
                    (int)$value['size'],
                    (int)$value['error'],
                    $value['name'],
                    $value['type']
                );
            }
        }
        return $normalized;
    }

}
