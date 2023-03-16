<?php
/**
 * ServerRequestInterface.php
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


namespace InitPHP\HTTP\Message\Interfaces;

interface ServerRequestInterface extends \Psr\Http\Message\ServerRequestInterface, RequestInterface
{

    /**
     * @param array $cookies
     * @return $this
     */
    public function setCookieParams(array $cookies): self;

    /**
     * @param array $query
     * @return $this
     */
    public function setQueryParams(array $query): self;

    /**
     * @param array $uploadedFiles
     * @return $this
     */
    public function setUploadedFiles(array $uploadedFiles): self;

    /**
     * @param array|object|null $data
     * @return $this
     */
    public function setParsedBody($data): self;

    /**
     * @param string $name
     * @param string $value
     * @return $this
     */
    public function setAttribute($name, $value): self;

    /**
     * @param string $name
     * @return $this
     */
    public function outAttribute($name): self;

    /**
     * @param array $files
     * @return array
     */
    public function normalizeFiles(array $files): array;

}
