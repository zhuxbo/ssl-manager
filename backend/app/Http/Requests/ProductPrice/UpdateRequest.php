<?php

namespace App\Http\Requests\ProductPrice;

use App\Http\Requests\BaseRequest;
use App\Models\ProductPrice;

class UpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        $productPriceId = $this->route('id');

        return [
            'level_code' => 'required|string|min:3|max:20|exists:user_levels,code',
            'period' => 'required|integer|min:1',
            'product_id' => [
                'required',
                'integer',
                'exists:products,id',
                function ($_attribute, $value, $fail) use ($productPriceId) {
                    // 只有当level_code和period都存在时才进行唯一性验证
                    if ($this->filled('level_code') && $this->filled('period')) {
                        $query = ProductPrice::where('product_id', $value)
                            ->where('level_code', $this->level_code)
                            ->where('period', $this->period);

                        if ($productPriceId) {
                            $query->where('id', '!=', $productPriceId);
                        }

                        if ($query->exists()) {
                            $fail('该产品、用户级别和周期的组合已经存在。');
                        }
                    }
                },
            ],
            'price' => 'required|numeric|min:0',
            'alternative_standard_price' => 'nullable|numeric|min:0',
            'alternative_wildcard_price' => 'nullable|numeric|min:0',
        ];
    }
}
