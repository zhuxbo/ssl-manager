<?php

namespace App\Http\Requests\Task;

use App\Http\Requests\BaseRequest;

class IndexRequest extends BaseRequest
{
    /**
     * 获取应用于请求的验证规则
     */
    public function rules(): array
    {
        return [
            'currentPage' => 'nullable|integer|min:1',
            'pageSize' => 'nullable|integer|min:1|max:100',
            'order_id' => 'nullable|integer',
            'action' => 'nullable|string',
            'source' => 'nullable|string',
            'status' => 'nullable|string|in:executing,successful,failed,stopped',
            'created_at' => 'nullable|array|size:2',
            'created_at.*' => 'string|date_format:Y-m-d\TH:i:s.v\Z',
        ];
    }

    /**
     * 获取已定义的验证规则的错误消息
     */
    public function messages(): array
    {
        return [
            'order_id.integer' => '订单ID必须是整数',
            'status.in' => '状态必须是执行中、已成功、已失败或已停止',
        ];
    }
}
