<?php

namespace App\Http\Requests\Product;

use App\Models\Product;
use Illuminate\Validation\Validator;

class StoreRequest extends BaseProductRequest
{
    public function rules(): array
    {
        $isSSL = $this->isSSL();
        $isCodeSign = $this->isCodeSign();

        $rules = [
            'code' => 'required|string|max:100|unique:products',
            'name' => 'required|string|max:100',
            'api_id' => 'required|string|max:128',
            'source' => 'required|string|max:20',
            'brand' => 'required|string|max:50',
            'ca' => 'required|string|max:200',
            'product_type' => 'required|string|in:ssl,codesign,smime,docsign',
            'warranty_currency' => 'nullable|in:$,€,¥',
            'warranty' => 'nullable|numeric|min:0',
            'server' => 'integer|min:0',
            'encryption_standard' => 'nullable|string|max:50',
            'encryption_alg' => 'nullable|array',
            'encryption_alg.*' => 'in:rsa,ecdsa,sm2',
            'signature_digest_alg' => 'nullable|array',
            'signature_digest_alg.*' => 'in:sha256,sha384,sha512,sm3',
            'validation_type' => 'required|string|max:50',
            'common_name_types' => 'nullable|array',  // SSL 产品必填，非 SSL 产品可选
            'common_name_types.*' => 'in:standard,wildcard,ipv4,ipv6,email,organization',
            'alternative_name_types' => 'array',
            'alternative_name_types.*' => 'in:standard,wildcard,ipv4,ipv6',
            'validation_methods' => 'nullable|array',
            'periods' => 'required|array',
            'periods.*' => 'integer|in:1,3,6,12,24,36,48,60,72,84,96,108,120',
            'standard_min' => 'integer|min:0',
            'standard_max' => 'integer|min:0',
            'wildcard_min' => 'integer|min:0',
            'wildcard_max' => 'integer|min:0',
            'total_min' => 'integer|min:1',
            'total_max' => 'integer|min:1',
            'add_san' => 'in:0,1',
            'replace_san' => 'in:0,1',
            'reissue' => 'nullable|in:0,1',
            'renew' => 'nullable|in:0,1',
            'reuse_csr' => 'nullable|in:0,1',
            'gift_root_domain' => 'in:0,1',
            'refund_period' => 'required|integer|min:0|max:30',
            'remark' => 'nullable|string|max:500',
            'weight' => 'required|integer|min:0',
            'status' => 'integer|in:0,1',
        ];

        // 所有产品类型都需要加密相关字段（用于 CSR 生成）
        $rules['encryption_standard'] = 'required|string|max:50';
        $rules['encryption_alg'] = 'required|array';
        $rules['signature_digest_alg'] = 'required|array';

        // 所有产品类型都需要续费和重用CSR字段
        $rules['renew'] = 'required|in:0,1';
        $rules['reuse_csr'] = 'required|in:0,1';

        // 非代码签名产品还需要重签字段（CodeSign 不支持重签）
        if (! $isCodeSign) {
            $rules['reissue'] = 'required|in:0,1';
        }

        // SSL 产品特有的必填字段
        if ($isSSL) {
            $rules['common_name_types'] = 'required|array';  // SSL 产品必填
            $rules['warranty_currency'] = 'required|in:$,€,¥';
            $rules['warranty'] = 'required|numeric|min:0';
            $rules['server'] = 'required|integer|min:0';
            $rules['validation_methods'] = 'required|array';
            $rules['standard_min'] = 'required|integer|min:0';
            $rules['standard_max'] = 'required|integer|min:0';
            $rules['wildcard_min'] = 'required|integer|min:0';
            $rules['wildcard_max'] = 'required|integer|min:0';
            $rules['total_min'] = 'required|integer|min:1';
            $rules['total_max'] = 'required|integer|min:1';
            $rules['add_san'] = 'required|in:0,1';
            $rules['replace_san'] = 'required|in:0,1';
            $rules['gift_root_domain'] = 'required|in:0,1';
        }

        return $rules;
    }

    /**
     * 获取验证后的数据，自动为非 SSL 产品设置默认值
     */
    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated($key, $default);

        if ($key !== null) {
            return $data;
        }

        // 非 SSL 产品，设置默认值并清除不适用字段
        if (! $this->isSSL()) {
            $this->setNonSSLDefaults($data);
        }

        // 代码签名产品，设置加密/签名/重签/CSR相关字段的默认值
        if ($this->isCodeSign()) {
            $this->setCodeSignDefaults($data);
        }

        return $data;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $data = $validator->getData();

            // 检查 source 和 api_id 组合的唯一性
            if (isset($data['source']) && isset($data['api_id'])) {
                $exists = Product::where('source', $data['source'])
                    ->where('api_id', $data['api_id'])
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('api_id', 'source 和 api_id 的组合必须唯一');
                }
            }

            // 只对 SSL 产品检查域名数量
            if ($this->isSSL()) {
                $this->validateSSLProductDomains($validator, $data);
            }

            // SMIME 产品 code 必须包含特定标记
            if ($this->isSMIME()) {
                $this->validateSMIMEProductCode($validator, $data);
            }
        });
    }

    /**
     * 验证 SMIME 产品 code 必须包含特定标记
     */
    protected function validateSMIMEProductCode(Validator $validator, array $data): void
    {
        $code = strtolower($data['code'] ?? '');
        $validTypes = ['mailbox', 'individual', 'sponsor', 'organization'];

        $found = false;
        foreach ($validTypes as $type) {
            if (str_contains($code, $type)) {
                $found = true;
                break;
            }
        }

        if (! $found) {
            $validator->errors()->add(
                'code',
                'S/MIME 产品 code 必须包含以下标记之一: mailbox, individual, sponsor, organization'
            );
        }
    }

}
