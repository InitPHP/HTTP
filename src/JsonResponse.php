<?php
/**
 * JsonResponse.php
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

use function json_encode;
use function json_decode;
use function is_string;
use function is_array;

/**
 * @since 1.0.1
 */
class JsonResponse extends Response
{

    private array $data = [];

    /**
     * @param string|array $data
     * @param int $status
     * @param string $version
     */
    public function __construct($data, int $status = 200, string $version = '1.1')
    {
        if(is_array($data)){
            $this->data = $data;
            $data = json_encode($data);
        }elseif(is_string($data)){
            $this->data = json_decode($data, true);
        }else{
            throw new \InvalidArgumentException('The $data parameter must be an array or json string.');
        }
        $body = new Stream($data, null);
        parent::__construct($status, ['Content-Type' => 'application/json'], $body, $version, null);
    }

    public function toArray(): array
    {
        return $this->data;
    }

}
