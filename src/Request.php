<?php
/**
 * Request.php
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

use \Psr\Http\Message\{StreamInterface, UriInterface, RequestInterface};

class Request implements RequestInterface
{

    use MessageTrait, RequestTrait;

    /**
     * @param string $method
     * @param UriInterface|string $uri
     * @param array $headers
     * @param StreamInterface|string|null|resource $body
     * @param string $version
     */
    public function __construct(string $method, $uri, array $headers = [], $body = null, string $version = '1.1')
    {
        if(!($uri instanceof UriInterface)){
            $uri = new Uri($uri);
        }
        $this->setUpConstruct($method, $uri, $body, $headers, $version);
    }

}
