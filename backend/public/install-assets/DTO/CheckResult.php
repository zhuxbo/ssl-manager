<?php

namespace Install\DTO;

/**
 * 检查结果数据对象
 */
class CheckResult
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_WARNING = 'warning';
    public const STATUS_ERROR = 'error';

    public function __construct(
        public bool $success,
        public string $name,
        public string $value,
        public string $status,
        public ?string $message = null,
    ) {}

    /**
     * 创建成功结果
     */
    public static function success(string $name, string $value, ?string $message = null): self
    {
        return new self(true, $name, $value, self::STATUS_SUCCESS, $message);
    }

    /**
     * 创建警告结果
     */
    public static function warning(string $name, string $value, ?string $message = null): self
    {
        return new self(true, $name, $value, self::STATUS_WARNING, $message);
    }

    /**
     * 创建错误结果
     */
    public static function error(string $name, string $value, ?string $message = null): self
    {
        return new self(false, $name, $value, self::STATUS_ERROR, $message);
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'name' => $this->name,
            'value' => $this->value,
            'status' => $this->status,
            'message' => $this->message,
        ];
    }
}
