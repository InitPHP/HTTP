<?php
/**
 * Request.php
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

use \InitPHP\HTTP\Message\Traits\{MessageTrait, RequestTrait};
use Psr\Http\Message\UriInterface;

class Request implements \InitPHP\HTTP\Message\Interfaces\RequestInterface
{

    use MessageTrait, RequestTrait;


    public function __construct(string $method, $uri, array $headers = [], $body = null, string $version = '1.1')
    {
        if(!($uri instanceof UriInterface)) {
            $uri = new Uri($uri);
        }
        $this->setUpConstruct($method, $uri, $body, $headers, $version);
    }

}
