<?php
/**
 * Response.php
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

namespace InitPHP\HTTP;

use InitPHP\HTTP\Message\Stream;
use Psr\Http\Message\ResponseInterface;

class Response extends \InitPHP\HTTP\Message\Response implements ResponseInterface
{

    public function __construct(int $status = 200, array $headers = [], $body = null, string $version = '1.1', ?string $reason = null)
    {
        parent::__construct($status, $headers, $body, $version, $reason);
    }


}
