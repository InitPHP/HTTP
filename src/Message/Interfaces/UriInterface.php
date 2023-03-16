<?php
/**
 * UriInterface.php
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

interface UriInterface extends \Psr\Http\Message\UriInterface
{

    /**
     * @param string $scheme
     * @return $this
     */
    public function setScheme(string $scheme): self;

    /**
     * @param string $user
     * @param string|null $password
     * @return $this
     */
    public function setUserInfo(string $user, ?string $password = null): self;

    /**
     * @param string $host
     * @return $this
     */
    public function setHost(string $host): self;

    /**
     * @param int|null $port
     * @return $this
     */
    public function setPort(?int $port): self;

    /**
     * @param string $path
     * @return $this
     */
    public function setPath(string $path): self;

    /**
     * @param string $query
     * @return $this
     */
    public function setQuery(string $query): self;

    /**
     * @param string $fragment
     * @return $this
     */
    public function setFragment(string $fragment): self;

}
