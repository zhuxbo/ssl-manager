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
        $rules['product_type'] = 'required|string|in:ssl,codesign,smime,docsign,acme';
        $rules['validation_type'] = 'required|string|max:50';
        $rules['periods'] = 'required|array';
        unset($rules['code']); // code 由 prepareForCreate 生成

        // 导入时这些字段允许 null，由 prepareForCreate 补默认值
        $rules['alternative_name_types'] = 'nullable|array';
        $rules['standard_min'] = 'nullable|integer|min:0';
        $rules['standard_max'] = 'nullable|integer|min:0';
        $rules['wildcard_min'] = 'nullable|integer|min:0';
        $rules['wildcard_max'] = 'nullable|integer|min:0';
        $rules['total_min'] = 'nullable|integer|min:0';
        $rules['total_max'] = 'nullable|integer|min:0';

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
        $data['alternative_name_types'] = $data['alternative_name_types'] ?? [];
        $data['validation_methods'] = $data['validation_methods'] ?? [];
        $data['standard_min'] = $data['standard_min'] ?? 0;
        $data['standard_max'] = $data['standard_max'] ?? 0;
        $data['wildcard_min'] = $data['wildcard_min'] ?? 0;
        $data['wildcard_max'] = $data['wildcard_max'] ?? 0;
        $data['total_min'] = $data['total_min'] ?? 0;
        $data['total_max'] = $data['total_max'] ?? 0;

        $productType = $data['product_type'] ?? 'ssl';
        if ($productType === 'acme') {
            $this->setAcmeDefaults($data);
        } elseif ($productType !== 'ssl' && ! empty($productType)) {
            $this->setNonSSLDefaults($data);
        }
        if ($productType === 'codesign') {
            $this->setCodeSignDefaults($data);
        }

        return $data;
    }
}
