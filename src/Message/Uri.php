<?php
/**
 * Uri.php
 *
 * This file is part of InitPHP HTTP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\HTTP\Message;

use \Psr\Http\Message\UriInterface;
use \InvalidArgumentException;

use function parse_url;
use function strtolower;
use function preg_replace_callback;
use function rawurlencode;
use function ltrim;
use function sprintf;
use function strpos;
use function preg_replace;

/**
 * PSR-7 UriInterface implementation. Parses an input URI string into its
 * RFC 3986 components (scheme, user info, host, port, path, query,
 * fragment) and exposes them through the standard PSR-7 accessors plus a
 * matching set of in-place setters used internally by the `with*()`
 * methods. Component-level encoding rules are applied on assignment so the
 * stored values are always emit-safe.
 */
class Uri implements UriInterface
{

    protected const SCHEMES = ['http' => 80, 'https' => 443];

    protected const CHAR_UNRESERVED = 'a-zA-Z0-9_\-\.~';

    protected const CHAR_SUB_DELIMS = '!\$&\'\(\)\*\+,;=';

    protected string $scheme = '';

    protected string $userInfo = '';

    protected string $host = '';

    protected ?int $port = null;

    protected string $path = '';

    protected string $query = '';

    protected string $fragment = '';

    /**
     * Build a Uri from a string. Empty input produces an empty Uri (all
     * components blank); a non-empty string is parsed with parse_url() and
     * each component is filtered for RFC 3986 compliance.
     *
     * @param  string $uri
     * @throws InvalidArgumentException When parse_url() fails, or a component (port, user info, ...) is invalid.
     */
    public function __construct(string $uri = '')
    {
        if($uri !== ''){
            if(FALSE === $parts = parse_url($uri)){
                throw new InvalidArgumentException(sprintf('Unable to parse URI: "%s"', $uri));
            }
            $this->scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
            $this->host = isset($parts['host']) ? strtolower($parts['host']) : '';
            $this->port = isset($parts['port']) ? $this->filterPort($parts['port']) : null;
            $this->path = isset($parts['path']) ? $this->filterPath($parts['path']) : '';
            $this->query = isset($parts['query']) ? $this->filterQueryAndFragment($parts['query']) : '';
            $this->fragment = isset($parts['fragment']) ? $this->filterQueryAndFragment($parts['fragment']) : '';
            if (isset($parts['user'])) {
                $this->userInfo = $this->filterUserInfoComponent($parts['user']);
                if (isset($parts['pass'])) {
                    $this->userInfo .= ':' . $this->filterUserInfoComponent($parts['pass']);
                }
            }
        }
    }

    /**
     * Recompose the URI string from its components per RFC 3986.
     *
     * @return string
     */
    public function __toString()
    {
        return self::createUriString($this->scheme, $this->getAuthority(), $this->path, $this->query, $this->fragment);
    }

    /**
     * Return the scheme component (lower-cased), or "" when absent.
     *
     * @return string
     */
    public function getScheme(): string
    {
        return $this->scheme;
    }

    /**
     * Return the authority component (user-info @ host : port), or "" when
     * the host is empty.
     *
     * @return string
     */
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

    /**
     * Return the user-info component (e.g. "user" or "user:password"),
     * percent-encoded as required by RFC 3986.
     *
     * @return string
     */
    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    /**
     * Return the host component (lower-cased), or "" when absent.
     *
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * Return the port component, or null when the URI uses the default
     * port for its scheme (or no port at all).
     *
     * @return int|null
     */
    public function getPort(): ?int
    {
        return $this->port;
    }

    /**
     * Return the path component. Paths beginning with "//" are collapsed
     * to a single leading "/" so the resulting URI cannot be misparsed as
     * having an authority component.
     *
     * @return string
     */
    public function getPath(): string
    {
        if(isset($this->path[0], $this->path[1]) && $this->path[0] === '/' && $this->path[1] === '/'){
            return '/' . ltrim($this->path, '/');
        }
        return $this->path;
    }

    /**
     * Return the query component (without the leading "?"), percent-encoded.
     *
     * @return string
     */
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * Return the fragment component (without the leading "#"), percent-encoded.
     *
     * @return string
     */
    public function getFragment(): string
    {
        return $this->fragment;
    }

    /**
     * Replace the scheme (in-place, lower-cased). Also re-runs the port
     * filter so the default port for the new scheme is hidden.
     *
     * @param  string $scheme
     * @return $this
     */
    public function setScheme(string $scheme): self
    {
        $scheme = strtolower($scheme);
        if ($this->scheme === $scheme) {
            return $this;
        }
        $this->scheme = $scheme;
        $this->port = $this->filterPort($this->port);

        return $this;
    }

    /**
     * Return a clone of the URI with the scheme replaced.
     *
     * @param  string $scheme
     * @return static
     */
    public function withScheme($scheme): self
    {
        return (clone $this)->setScheme($scheme);
    }

    /**
     * Replace the user-info component (in-place), percent-encoding both
     * the user and the optional password.
     *
     * @param  string      $user
     * @param  string|null $password
     * @return $this
     */
    public function setUserInfo(string $user, ?string $password = null): self
    {
        $info = $this->filterUserInfoComponent($user);
        if ($password !== null && $password !== '') {
            $info .= ':' . $this->filterUserInfoComponent($password);
        }
        if ($this->userInfo === $info) {
            return $this;
        }
        $this->userInfo = $info;

        return $this;
    }

    /**
     * Return a clone of the URI with the user-info component replaced.
     *
     * @param  string      $user
     * @param  string|null $password
     * @return static
     */
    public function withUserInfo($user, $password = null): self
    {
        return (clone $this)->setUserInfo($user, $password);
    }

    /**
     * Replace the host (in-place, lower-cased).
     *
     * @param  string $host
     * @return $this
     */
    public function setHost(string $host): self
    {
        $host = strtolower($host);
        if ($this->host === $host) {
            return $this;
        }
        $this->host = $host;

        return $this;
    }

    /**
     * Return a clone of the URI with the host replaced.
     *
     * @param  string $host
     * @return static
     */
    public function withHost($host): self
    {
        return (clone $this)->setHost($host);
    }

    /**
     * Replace the port (in-place). Default ports for the current scheme
     * are normalised to null by {@see Uri::filterPort()}.
     *
     * @param  int|null $port
     * @return $this
     * @throws InvalidArgumentException When $port is outside the 0..65535 range.
     */
    public function setPort(?int $port): self
    {
        if ($this->port === $port = $this->filterPort($port)) {
            return $this;
        }
        $this->port = $port;

        return $this;
    }

    /**
     * Return a clone of the URI with the port replaced.
     *
     * @param  int|null $port
     * @return static
     * @throws InvalidArgumentException When $port is outside the 0..65535 range.
     */
    public function withPort($port): self
    {
        return (clone $this)->setPort($port);
    }

    /**
     * Replace the path (in-place), percent-encoding any reserved or
     * non-allowed characters per RFC 3986.
     *
     * @param  string $path
     * @return $this
     */
    public function setPath(string $path): self
    {
        if ($this->path === $path = $this->filterPath($path)) {
            return $this;
        }
        $this->path = $path;

        return $this;
    }

    /**
     * Return a clone of the URI with the path replaced.
     *
     * @param  string $path
     * @return static
     */
    public function withPath($path): self
    {
        return (clone $this)->setPath($path);
    }

    /**
     * Replace the query string (in-place), percent-encoding any reserved
     * or non-allowed characters.
     *
     * @param  string $query
     * @return $this
     */
    public function setQuery(string $query): self
    {
        if($this->query === $query = $this->filterQueryAndFragment($query)){
            return $this;
        }
        $this->query = $query;

        return $this;
    }

    /**
     * Return a clone of the URI with the query string replaced.
     *
     * @param  string $query
     * @return static
     */
    public function withQuery($query): self
    {
        return (clone $this)->setQuery($query);
    }

    /**
     * Replace the fragment (in-place), percent-encoding any reserved or
     * non-allowed characters.
     *
     * @param  string $fragment
     * @return $this
     */
    public function setFragment(string $fragment): self
    {
        if($this->fragment === $fragment = $this->filterQueryAndFragment($fragment)){
            return $this;
        }
        $this->fragment = $fragment;

        return $this;
    }

    /**
     * Return a clone of the URI with the fragment replaced.
     *
     * @param  string $fragment
     * @return static
     */
    public function withFragment($fragment): self
    {
        return (clone $this)->setFragment($fragment);
    }

    /**
     * Recompose a URI string from its individual components per RFC 3986.
     *
     * @param  string $scheme
     * @param  string $authority
     * @param  string $path
     * @param  string $query
     * @param  string $fragment
     * @return string
     */
    protected static function createUriString(string $scheme, string $authority, string $path, string $query, string $fragment): string
    {
        $uri = '';
        if($scheme !== ''){
            $uri .= $scheme . ':';
        }
        if($authority !== '' || $scheme === 'file'){
            $uri .= '//' . $authority;
        }

        if($path !== ''){
            if($path[0] !== '/'){
                if($authority !== ''){
                    $path = '/' . $path;
                }
            }elseif(isset($path[1]) && $path[1] === '/'){
                if($authority === ''){
                    $path = '/' . ltrim($path, '/');
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

    /**
     * True when $port is not the default port for $scheme (so the port
     * must be rendered explicitly in the authority component).
     *
     * @param  string $scheme
     * @param  int    $port
     * @return bool
     */
    protected static function isNonStandardPort(string $scheme, int $port): bool
    {
        return !isset(self::SCHEMES[$scheme]) || $port !== self::SCHEMES[$scheme];
    }

    /**
     * Validate $port and normalise default ports for the current scheme
     * to null so they are omitted from the rendered URI.
     *
     * @param  int|string|null $port
     * @return int|null
     * @throws InvalidArgumentException When $port is outside the 0..65535 range.
     */
    protected function filterPort($port): ?int
    {
        if($port === null){
            return null;
        }
        $port = (int)$port;
        if($port < 0 || $port > 0xffff){
            throw new InvalidArgumentException(sprintf('Invalid port %d. Must be between 0 and 65535', $port));
        }
        return self::isNonStandardPort($this->scheme, $port) ? $port : null;
    }

    /**
     * Percent-encode characters not allowed in a URI path per RFC 3986.
     *
     * @param  string $path
     * @return string
     */
    protected function filterPath(string $path): string
    {
        return preg_replace_callback(
            '/(?:[^' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . '%:@\/]++|%(?![A-Fa-f0-9]{2}))/',
            [__CLASS__, 'rawurlencodeMatchZero'],
            $path
        );
    }

    /**
     * Percent-encode characters not allowed in a query string or fragment
     * per RFC 3986.
     *
     * @param  string $str
     * @return string
     */
    protected function filterQueryAndFragment(string $str): string
    {
        return preg_replace_callback(
            '/(?:[^' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . '%:@\/\?]++|%(?![A-Fa-f0-9]{2}))/',
            [__CLASS__, 'rawurlencodeMatchZero'],
            $str
        );
    }

    /**
     * Percent-encode characters not allowed in a single user-info
     * component (user or password) per RFC 3986.
     *
     * @param  string $component
     * @return string
     */
    protected function filterUserInfoComponent(string $component): string
    {
        return preg_replace_callback('/(?:[^%' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . ']+|%(?![A-Fa-f0-9]{2}))/', [__CLASS__, 'rawurlencodeMatchZero'], $component);
    }

    /**
     * preg_replace_callback() helper that returns the rawurlencode() of
     * the entire matched substring.
     *
     * @param  array{0:string} $match
     * @return string
     */
    protected static function rawurlencodeMatchZero(array $match): string
    {
        return rawurlencode($match[0]);
    }

}
