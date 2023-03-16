<?php
/**
 * ResponseInterface.php
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

use Psr\Http\Message\UriInterface;

interface ResponseInterface extends \Psr\Http\Message\ResponseInterface, MessageInterface
{

    /**
     * @param int $code
     * @param string $reasonPhrase
     * @return $this
     */
    public function setStatusCode(int $code, string $reasonPhrase = ''): self;

    /**
     * @param \Psr\Http\Message\StreamInterface|resource|scalar|string|null $body
     * @return $this
     */
    public function setStream($body): self;

    /**
     * @param array $data
     * @param int $status
     * @return $this
     */
    public function json(array $data = [], int $status = 200): self;

    /**
     * @param UriInterface|string $uri
     * @param int $status
     * @param int $second
     * @return $this
     */
    public function redirect($uri, int $status = 302, int $second = 0): self;

}
