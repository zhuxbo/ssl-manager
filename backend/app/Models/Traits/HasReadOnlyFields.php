<?php

namespace App\Models\Traits;

trait HasReadOnlyFields
{
    /**
     * 启动只读字段功能
     */
    protected static function bootHasReadOnlyFields(): void
    {
        static::updating(function ($model) {
            $model->protectReadOnlyFields();
        });
    }

    /**
     * 保护只读字段不被修改
     */
    protected function protectReadOnlyFields(): void
    {
        foreach ($this->getReadOnlyFields() as $field) {
            if ($this->isDirty($field) && $this->getOriginal($field) !== null) {
                // 恢复原始值
                $this->attributes[$field] = $this->getOriginal($field);
            }
        }
    }

    /**
     * 检查字段是否为只读
     */
    public function isReadOnlyField(string $field): bool
    {
        return in_array($field, $this->getReadOnlyFields());
    }

    /**
     * 获取所有只读字段
     * 子类应该定义 protected array $readOnlyFields 属性
     */
    public function getReadOnlyFields(): array
    {
        return property_exists($this, 'readOnlyFields') ? $this->readOnlyFields : [];
    }
}
