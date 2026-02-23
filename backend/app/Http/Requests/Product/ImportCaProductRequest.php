<?php

namespace App\Http\Requests\Product;

use App\Models\Product;
use Illuminate\Validation\Validator;

class ImportCaProductRequest extends UpdateRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['api_id'] = 'required|string|max:100';
        $rules['source'] = 'required|string|max:20';
        $rules['brand'] = 'required|string|max:50';
        $rules['ca'] = 'required|string|max:200';
        $rules['product_type'] = 'required|string|in:ssl,codesign,smime,docsign';
        $rules['validation_type'] = 'required|string|max:50';
        $rules['periods'] = 'required|array';
        unset($rules['code']); // code 由 prepareForCreate 生成

        return $rules;
    }

    /**
     * 仅保留 source+api_id 唯一性检查
     * 跳过 SSL 域名数量校验（导入数据不含域名限制，standard_max ?? 0 会导致 SSL 产品误报错误）
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $data = $validator->getData();
            if (isset($data['source']) && isset($data['api_id'])) {
                if (Product::where('source', $data['source'])->where('api_id', $data['api_id'])->exists()) {
                    $validator->errors()->add('api_id', 'source 和 api_id 的组合必须唯一');
                }
            }
        });
    }

    /**
     * 生成 code、设置 NOT NULL 字段默认值，非 SSL / CodeSign 产品设置对应默认值
     */
    public function prepareForCreate(array $data): array
    {
        $data['code'] = $data['api_id'];
        $data['name'] = $data['name'] ?? $data['ca'].'_'.$data['api_id'];
        $data['encryption_alg'] = $data['encryption_alg'] ?? ['rsa'];
        $data['signature_digest_alg'] = $data['signature_digest_alg'] ?? ['sha256'];
        $data['common_name_types'] = $data['common_name_types'] ?? [];
        $data['validation_methods'] = $data['validation_methods'] ?? [];

        $productType = $data['product_type'] ?? 'ssl';
        if ($productType !== 'ssl' && ! empty($productType)) {
            $this->setNonSSLDefaults($data);
        }
        if ($productType === 'codesign') {
            $this->setCodeSignDefaults($data);
        }

        return $data;
    }
}
