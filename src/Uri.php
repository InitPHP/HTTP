<?php
/**
 * Uri.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    http://initphp.github.com/license.txt  MIT
 * @version    1.0.3
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\HTTP;

use \Psr\Http\Message\UriInterface;

use function parse_url;
use function strtolower;
use function is_string;
use function preg_replace_callback;
use function rawurlencode;

class Uri implements UriInterface
{

    protected const SCHEMES = ['http' => 80, 'https' => 443];

    protected const CHAR_UNRESERVED = 'a-zA-Z0-9_\-\.~';

    protected const CHAR_SUB_DELIMS = '!\$&\'\(\)\*\+,;=';

    protected string $scheme = '';

    protected string $userInfo = '';

    protected string $host = '';

    protected ?int $port;

    protected string $path = '';

    protected string $query = '';

    protected string $fragment = '';

    public function __construct(string $uri = '')
    {
        if($uri !== ''){
            if(FALSE === $parts = parse_url($uri)){
                throw new \InvalidArgumentException(\sprintf('Unable to parse URI: "%s"', $uri));
            }
            $this->scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
            $this->userInfo = $parts['user'] ?? '';
            $this->host = isset($parts['host']) ? strtolower($parts['host']) : '';
            $this->port = isset($parts['port']) ? $this->filterPort($parts['port']) : null;
            $this->path = isset($parts['path']) ? $this->filterPath($parts['path']) : '';
            $this->query = isset($parts['query']) ? $this->filterQueryAndFragment($parts['query']) : '';
            $this->fragment = isset($parts['fragment']) ? $this->filterQueryAndFragment($parts['fragment']) : '';
            if (isset($parts['pass'])) {
                $this->userInfo .= ':' . $parts['pass'];
            }
        }
    }

    public function __toString()
    {
        return self::createUriString($this->scheme, $this->getAuthority(), $this->path, $this->query, $this->fragment);
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getAuthority(): string
    {
        if($this->host === ''){
            return '';
        }
        $authority = $this->host;
        if($this->userInfo !== ''){
            $authority = $this->userInfo . '@' . $authority;
        }
        if($this->port !== null){
            $authority .= ':' . $this->port;
        }
        return $authority;
    }


    public function getUserInfo(): string
    {
        return $this->userInfo;
    }


    public function getHost(): string
    {
        return $this->host;
    }


    public function getPort(): ?int
    {
        return $this->port;
    }


    public function getPath(): string
    {
        return $this->path;
    }


    public function getQuery(): string
    {
        return $this->query;
    }


    public function getFragment(): string
    {
        return $this->fragment;
    }


    public function withScheme($scheme): self
    {
        if(!is_string($scheme)){
            throw new \InvalidArgumentException('Scheme must be a string');
        }
        if($this->scheme === $scheme = strtolower($scheme)){
            return $this;
        }
        $clone = clone $this;
        $clone->scheme = $scheme;
        $clone->port = $clone->filterPort($clone->port);
        return $clone;
    }


    public function withUserInfo($user, $password = null): self
    {
        $info = $user;
        if($password !== null && $password !== ''){
            $info .= ':' . $password;
        }
        if($this->userInfo === $info){
            return $this;
        }
        $clone = clone $this;
        $clone->userInfo = $info;
        return $clone;
    }

    public function withHost($host): self
    {
        if(!is_string($host)){
            throw new \InvalidArgumentException('Host must be a string');
        }
        if($this->host === $host = strtolower($host)){
            return $this;
        }
        $clone = clone $this;
        $clone->host = $host;
        return $clone;
    }

    public function withPort($port): self
    {
        if($this->port === $port = $this->filterPort($port)){
            return $this;
        }
        $clone = clone $this;
        $clone->port = $port;
        return $clone;
    }

    public function withPath($path): self
    {
        if($this->path === $path = $this->filterPath($path)){
            return $this;
        }
        $clone = clone $this;
        $clone->path = $path;
        return $clone;
    }

    public function withQuery($query): self
    {
        if($this->query === $query = $this->filterQueryAndFragment($query)){
            return $this;
        }
        $clone = clone $this;
        $clone->query = $query;
        return $clone;
    }

    public function withFragment($fragment): self
    {
        if($this->fragment === $fragment = $this->filterQueryAndFragment($fragment)){
            return $this;
        }
        $clone = clone $this;
        $clone->fragment = $fragment;
        return $clone;
    }

    protected static function createUriString(string $scheme, string $authority, string $path, string $query, string $fragment): string
    {
        $uri = '';
        if($scheme !== ''){
            $uri .= $scheme . ':';
        }
        if($scheme === 'file'){
            $uri .= '///';
        }
        if($authority !== ''){
            $uri .= '//' . $authority;
        }

        if($path !== ''){
            if($path[0] !== '/'){
                if($authority !== ''){
                    $path = '/' . $path;
                }
            }elseif(isset($path[1]) && $path[1] === '/'){
                if($authority === ''){
                    $path = '/' . \ltrim($path, '/');
                }
            }
            $uri .= $path;
        }

        if($query !== ''){
            $uri .= '?' . $query;
        }

        if($fragment !== ''){
            $uri .= '#' . $fragment;
        }

        return $uri;
    }

    protected static function isNonStandardPort(string $scheme, int $port): bool
    {
        return !isset(self::SCHEMES[$scheme]) || $port !== self::SCHEMES[$scheme];
    }

    protected function filterPort($port): ?int
    {
        if($port === null){
            return null;
        }
        $port = (int)$port;
        if($port < 0 || $port > 0xffff){
            throw new \InvalidArgumentException(\sprintf('Invalid port %d. Must be between 0 and 65535', $port));
        }
        return self::isNonStandardPort($this->scheme, $port) ? $port : null;
    }

    protected function filterPath($path): string
    {
        if(!is_string($path)){
            throw new \InvalidArgumentException('Path must be a string');
        }
        return preg_replace_callback('/(?:[^' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . '%:@\/]++|%(?![A-Fa-f0-9]{2}))/', [__CLASS__, 'rawurlencodeMatchZero'], $path);
    }

    protected function filterQueryAndFragment($str): string
    {
        if (!is_string($str)) {
            throw new \InvalidArgumentException('Query and fragment must be a string');
        }
        return preg_replace_callback('/(?:[^' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . '%:@\/\?]++|%(?![A-Fa-f0-9]{2}))/', [__CLASS__, 'rawurlencodeMatchZero'], $str);
    }

    protected static function rawurlencodeMatchZero(array $match): string
    {
        return rawurlencode($match[0]);
    }

}