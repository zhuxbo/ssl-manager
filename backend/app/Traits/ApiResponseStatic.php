<?php

namespace App\Traits;

use App\Exceptions\ApiResponseException;

trait ApiResponseStatic
{
    /**
     * 返回成功的操作
     */
    protected static function success(?array $data = null): void
    {
        throw new ApiResponseException('', null, $data, 1);
    }

    /**
     * 返回失败的操作
     */
    protected static function error(string $msg = '', ?array $errors = null): void
    {
        throw new ApiResponseException($msg, $errors, null, 0);
    }

    /**
     * 返回 JSON 数据格式
     */
    protected static function response(string $msg = '', ?array $errors = null, ?array $data = null, int $code = 0): void
    {
        throw new ApiResponseException($msg, $errors, $data, $code);
    }
}
