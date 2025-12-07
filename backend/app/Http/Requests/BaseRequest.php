<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class BaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * 处理请求参数
     */
    protected function prepareForValidation(): void
    {
        if (isset($this->ids) && is_string($this->ids) && ! empty($this->ids)) {
            $this->merge([
                'ids' => explode(',', $this->ids),
            ]);
        }
    }

    /**
     * 配置验证器
     */
    public function withValidator(Validator $validator): void
    {
        // 检查是否为用户路由，如果是则尝试移除 user_id 验证规则
        if ($this->isUserRoute()) {
            $rules = $validator->getRules();

            if (isset($rules['user_id'])) {
                unset($rules['user_id']);
                $validator->setRules($rules);
            }
        }
    }

    /**
     * 判断当前请求是否为用户路由（是否应用了 api.user 中间件）
     */
    protected function isUserRoute(): bool
    {
        $route = $this->route();
        if (! $route) {
            return false;
        }

        // 获取当前路由使用的中间件
        $middlewares = $route->gatherMiddleware();

        // 检查是否包含 api.user 中间件
        foreach ($middlewares as $middleware) {
            if ($middleware === 'api.user') {
                return true;
            }
        }

        // 检查控制器中间件（如果无法通过路由中间件判断）
        $action = $route->getAction();
        if (isset($action['middleware']) && is_array($action['middleware'])) {
            if (in_array('api.user', $action['middleware'], true)) {
                return true;
            }
        }

        return false;
    }
}
