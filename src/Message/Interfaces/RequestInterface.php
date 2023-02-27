<?php
/**
 * RequestInterface.php
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

use \Psr\Http\Message\{StreamInterface, UriInterface};

interface RequestInterface extends \Psr\Http\Message\RequestInterface, MessageInterface
{

    /**
     * @param string $requestTarget
     * @return $this
     */
    public function setRequestTarget($requestTarget): self;

    /**
     * @param string ...$methods
     * @return bool
     */
    public function isMethod(string ...$methods): bool;

    /**
     * @return bool
     */
    public function isGet(): bool;

    /**
     * @return bool
     */
    public function isPost(): bool;

    /**
     * @return bool
     */
    public function isPut(): bool;

    /**
     * @return bool
     */
    public function isDelete(): bool;

    /**
     * @return bool
     */
    public function isHead(): bool;

    /**
     * @return bool
     */
    public function isPatch(): bool;

    /**
     * @param string $method
     * @return $this
     */
    public function setMethod($method): self;

    /**
     * @param UriInterface $uri
     * @param bool $preserveHost
     * @return $this
     */
    public function setUri(UriInterface $uri, bool $preserveHost = false): self;

    /**
     * @param StreamInterface $body
     * @return $this
     */
    public function setBody(StreamInterface $body): self;

}
