<?php

namespace App\Http\Requests\ApiToken;

use App\Http\Requests\BaseRequest;

class UpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        $apiTokenId = $this->route('id', 0);

        return [
            'user_id' => 'required|integer|exists:users,id|unique:api_tokens,user_id,'.$apiTokenId,
            'token' => 'nullable|string|max:128',
            'allowed_ips' => 'nullable|array',
            'allowed_ips.*' => 'ip',
            'rate_limit' => 'nullable|integer',
            'status' => 'nullable|integer',
        ];
    }

    /**
     * 处理请求参数，过滤allowed_ips中的空值
     */
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if (isset($this->allowed_ips) && is_array($this->allowed_ips)) {
            $this->merge([
                'allowed_ips' => array_values(array_filter($this->allowed_ips, function ($ip) {
                    return ! empty(trim($ip));
                })),
            ]);
        }
    }

    /**
     * 获取验证后的数据，并移除空的 token 字段
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // 如果是获取所有验证数据（$key 为 null），且 token 为空，则移除 token 字段
        if ($key === null && is_array($validated) && array_key_exists('token', $validated) && empty($validated['token'])) {
            unset($validated['token']);
        }

        return $validated;
    }
}
