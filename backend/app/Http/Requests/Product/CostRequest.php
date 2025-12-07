<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Validator;

class CostRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'cost' => 'required|array',
            'cost.price' => 'required|array',
            'cost.price.*' => 'required|numeric|min:0',
            'cost.alternative_standard_price' => 'nullable|array',
            'cost.alternative_standard_price.*' => 'nullable|numeric|min:0',
            'cost.alternative_wildcard_price' => 'nullable|array',
            'cost.alternative_wildcard_price.*' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * 自定义验证消息
     */
    public function messages(): array
    {
        return [
            'cost.required' => '请提供成本数据',
            'cost.array' => '成本数据格式不正确',
            'cost.price.required' => '请提供基础价格数据',
            'cost.price.array' => '基础价格数据格式不正确',
            'cost.price.*.required' => '基础价格不能为空',
            'cost.price.*.numeric' => '基础价格必须为数字',
            'cost.price.*.min' => '基础价格不能小于0',
            'cost.alternative_standard_price.array' => '标准域名价格数据格式不正确',
            'cost.alternative_standard_price.*.numeric' => '标准域名价格必须为数字',
            'cost.alternative_standard_price.*.min' => '标准域名价格不能小于0',
            'cost.alternative_wildcard_price.array' => '通配符价格数据格式不正确',
            'cost.alternative_wildcard_price.*.numeric' => '通配符价格必须为数字',
            'cost.alternative_wildcard_price.*.min' => '通配符价格不能小于0',
        ];
    }

    /**
     * 验证后处理数据
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $data = $validator->getData();

            // 检查是否有价格数据
            if (empty($data['cost']) || empty($data['cost']['price'])) {
                $validator->errors()->add('cost.price', '基础价格数据不能为空');

                return;
            }

            // 检查每个周期的价格是否合理
            foreach ($data['cost']['price'] as $period => $price) {
                // 确保周期是数字
                if (! is_numeric($period)) {
                    $validator->errors()->add("cost.price.$period", "周期 $period 必须是数字");

                    continue;
                }

                // 如果设置了标准域名价格和通配符价格，确保通配符价格大于等于标准域名价格
                $standardPrice = $data['cost']['alternative_standard_price'][$period] ?? null;
                $wildcardPrice = $data['cost']['alternative_wildcard_price'][$period] ?? null;

                if (
                    $standardPrice !== null &&
                    $wildcardPrice !== null &&
                    $wildcardPrice < $standardPrice
                ) {
                    $validator->errors()->add(
                        "cost.alternative_wildcard_price.$period",
                        "周期 $period 的通配符价格应大于等于标准域名价格"
                    );
                }
            }
        });
    }
}
