<?php

namespace App\Traits;

trait LogSanitizer
{
    /**
     * 需要过滤的敏感字段
     */
    protected array $sensitiveFields = [
        'password',
        'password_confirmation',
        'auth_password',
        'auth_token',
        'token',
        'access_token',
        'refresh_token',
        'api_token',
        'bearer_token',
        'authorization',
        'csr',
        'private_key',
        'cert',
        'intermediate_cert',
        'pass',
        'secret',
        'key',
        'api_key',
        'client_secret',
        'session_id',
        'csrf_token',
        'direct_login_url',
    ];

    /**
     * 需要过滤的敏感字段模式（正则匹配）
     */
    protected array $sensitivePatterns = [
        '/.*token.*/i',
        '/.*password.*/i',
        '/.*secret.*/i',
        '/.*key.*/i',
        '/.*auth.*/i',
    ];

    /**
     * 清理请求参数
     */
    protected function sanitizeParams(array $params): array
    {
        return $this->removeSensitiveData($params);
    }

    /**
     * 清理响应内容
     * 调整：若 $response 为空，则返回 null
     */
    protected function sanitizeResponse(mixed $response): ?array
    {
        // 空响应直接返回 null
        if (empty($response)) {
            return null;
        }

        // 如果是 JSON 字符串，先尝试解码
        if (is_string($response) && $this->isJson($response)) {
            $response = json_decode($response, true);
        }

        // 如果不是数组，转换成统一的数组结构
        if (! is_array($response)) {
            $response = ['content' => (string) $response];
        }

        return $this->removeSensitiveData($response);
    }

    /**
     * 检查字符串是否为合法 JSON
     */
    protected function isJson(string $string): bool
    {
        // Laravel 11 已提供 json_validate()
        return json_validate($string);
    }

    /**
     * 移除敏感字段
     */
    protected function removeSensitiveData(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($this->isSensitiveField($key) && ! empty($value)) {
                $data[$key] = '******'; // 替换敏感信息
            } elseif (is_array($value)) {
                $data[$key] = $this->removeSensitiveData($value);
            }
        }

        return $data;
    }

    /**
     * 检查字段名是否为敏感字段
     */
    protected function isSensitiveField(string $fieldName): bool
    {
        // 精确匹配
        if (in_array(strtolower($fieldName), array_map('strtolower', $this->sensitiveFields))) {
            return true;
        }

        // 模式匹配
        foreach ($this->sensitivePatterns as $pattern) {
            if (preg_match($pattern, $fieldName)) {
                return true;
            }
        }

        return false;
    }
}
