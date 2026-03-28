<?php

namespace App\Traits;

use App\Exceptions\ApiResponseException;

trait ApiResponse
{
    /**
     * 返回成功的操作
     */
    protected function success(?array $data = null): never
    {
        throw new ApiResponseException('', null, $data, 1);
    }

    /**
     * 返回失败的操作
     */
    protected function error(string $msg = '', ?array $errors = null): never
    {
        throw new ApiResponseException($msg, $errors, null, 0);
    }

    /**
     * 返回 JSON 数据格式
     */
    protected function response(string $msg = '', ?array $errors = null, ?array $data = null, int $code = 0): never
    {
        throw new ApiResponseException($msg, $errors, $data, $code);
    }
}
