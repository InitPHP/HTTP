<?php
/**
 * MessageInterface.php
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

interface MessageInterface extends \Psr\Http\Message\MessageInterface
{

    /**
     * @param string $version
     * @return $this
     */
    public function setProtocolVersion(string $version): self;

    /**
     * @param array $headers
     * @return $this
     */
    public function setHeaders(array $headers): self;

    /**
     * @param string $name
     * @param string $value
     * @return $this
     */
    public function setHeader($name, $value): self;

    /**
     * @param string $name
     * @param string $value
     * @return $this
     */
    public function addedHeader($name, $value): self;

    /**
     * @param string $name
     * @return $this
     */
    public function outHeader($name): self;

}
