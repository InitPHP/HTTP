<?php
/**
 * RedirectResponse.php
 *
 * This file is part of HTTP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.0.3
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\HTTP;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

use function is_string;

/**
 * @since 1.0.2
 */
class RedirectResponse extends Response
{
    /**
     * @param string|UriInterface $url
     * @param int $status
     * @param int $second
     */
    public function __construct($url, int $status = 200, int $second = 0)
    {
        if($url instanceof UriInterface){
            $url = $url->__toString();
        }
        if(!is_string($url)){
            throw new \InvalidArgumentException('The $url parameter must be an UriInterface or string.');
        }
        if($second < 1){
            $headers = [
                'Location'  => $url,
            ];
        }else{
            $headers = [
                'Refresh'   => $second . '; url=' . $url,
            ];
        }
        parent::__construct($status, $headers, new Stream('', null), '1.1');
    }

}
