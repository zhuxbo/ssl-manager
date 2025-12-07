<?php

namespace App\Exceptions;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class ApiResponseException extends HttpResponseException
{
    protected array $apiResponse;

    /**
     * 创建一个新的异常实例
     */
    public function __construct(string $msg = '', ?array $errors = null, ?array $data = null, int $code = 0)
    {
        $this->apiResponse['code'] = $code;

        // 错误响应
        if ($code === 0) {
            $this->apiResponse['msg'] = $msg;
            $errors !== null && $this->apiResponse['errors'] = $errors;
        }

        // 成功响应
        if ($code === 1) {
            $data !== null && $this->apiResponse['data'] = $data;
            // 如果有消息，也添加到响应中
            ! empty($msg) && $this->apiResponse['msg'] = $msg;
        }

        parent::__construct(new JsonResponse($this->apiResponse));
    }

    /**
     * 获取响应
     */
    public function getApiResponse(): array
    {
        return $this->apiResponse;
    }
}
