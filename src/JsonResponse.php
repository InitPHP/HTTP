<?php
/**
 * JsonResponse.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    http://initphp.github.com/license.txt  MIT
 * @version    1.0.1
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\HTTP;

use function json_encode;

/**
 * @since 1.0.1
 */
class JsonResponse extends Response
{
    public function __construct(array $data, int $status = 200, string $version = '1.1')
    {
        $body = new Stream(json_encode($data), null);
        parent::__construct($status, ['Content-Type' => 'application/json'], $body, $version, null);
    }
}
