<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\BaseRequest;

class ExportRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'brands' => 'nullable|array',
            'brands.*' => 'string|max:50',
            'levelCustom' => 'nullable|integer|in:0,1',
            'levelCodes' => 'nullable|array',
            'levelCodes.*' => 'string|max:20',
            'priceRate' => 'nullable|numeric|min:0.01|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'brands.array' => '品牌参数格式错误',
            'brands.*.string' => '品牌名称必须为字符串',
            'brands.*.max' => '品牌名称不能超过50个字符',
            'levelCustom.integer' => '会员级别类型必须为整数',
            'levelCustom.in' => '会员级别类型只能为0或1',
            'levelCodes.array' => '会员级别参数格式错误',
            'levelCodes.*.string' => '会员级别代码必须为字符串',
            'levelCodes.*.max' => '会员级别代码不能超过20个字符',
            'priceRate.numeric' => '价格倍率必须为数字',
            'priceRate.min' => '价格倍率不能小于0.01',
            'priceRate.max' => '价格倍率不能大于1000',
        ];
    }
}
