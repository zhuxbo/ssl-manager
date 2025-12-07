<?php

namespace App\Models\Scopes;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class UserScope implements Scope
{
    protected int|string $user_id;

    /**
     * 创建一个新的用户作用域
     */
    public function __construct($userId)
    {
        $this->user_id = $userId;
    }

    public function apply(Builder $builder, Model $model): void
    {
        if ($this->user_id) {
            $builder->where('user_id', $this->user_id);
        }
    }

    /**
     * 批量为固定模型添加作用域
     */
    public static function addScopeToModels(int $userId, array $models = []): void
    {
        $models = array_unique(array_merge([Order::class], $models));

        foreach ($models as $model) {
            if (class_exists($model)) {
                $model::addGlobalScope(new self($userId));
            }
        }
    }
}
