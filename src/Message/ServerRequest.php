<?php
/**
 * ServerRequest.php
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

use \InitPHP\HTTP\Message\Interfaces\ServerRequestInterface;
use \InitPHP\HTTP\Message\Traits\{MessageTrait, RequestTrait};
use \Psr\Http\Message\{UploadedFileInterface, UriInterface};

use function array_keys;
use function is_array;
use function is_object;

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

    /**
     * @inheritDoc
     */
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    /**
     * @inheritDoc
     */
    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    /**
     * @inheritDoc
     */
    public function setCookieParams(array $cookies): self
    {
        $this->cookieParams = $cookies;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withCookieParams(array $cookies): ServerRequest
    {
        return (clone $this)->setCookieParams($cookies);
    }

    /**
     * @inheritDoc
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * @inheritDoc
     */
    public function setQueryParams(array $query): self
    {
        $this->queryParams = $query;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withQueryParams(array $query): ServerRequest
    {
        return (clone $this)->setQueryParams($query);
    }

    /**
     * @inheritDoc
     */
    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    /**
     * @inheritDoc
     */
    public function setUploadedFiles(array $uploadedFiles): self
    {
        $this->uploadedFiles = $this->normalizeFiles($uploadedFiles);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withUploadedFiles(array $uploadedFiles): self
    {
        return (clone $this)->setUploadedFiles($uploadedFiles);
    }

    /**
     * @inheritDoc
     */
    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    /**
     * @inheritDoc
     */
    public function setParsedBody($data): self
    {
        if(!is_array($data) && !is_object($data) && $data !== null){
            throw new \InvalidArgumentException('First parameter to withParsedBody MUST be object, array or null');
        }
        $this->parsedBody = $data;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withParsedBody($data): self
    {
        return (clone $this)->setParsedBody($data);
    }

    /**
     * @inheritDoc
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @inheritDoc
     */
    public function getAttribute($name, $default = null)
    {
        return $this->attributes[$name] ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function setAttribute($name, $value): self
    {
        $this->attributes[$name] = $value;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withAttribute($name, $value): self
    {
        return (clone $this)->setAttribute($name, $value);
    }

    /**
     * @inheritDoc
     */
    public function outAttribute($name): self
    {
        if (!isset($this->attributes[$name])) {
            return $this;
        }
        unset($this->attributes[$name]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withoutAttribute($name): self
    {
        return (clone $this)->outAttribute($name);
    }

    /**
     * @inheritDoc
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
