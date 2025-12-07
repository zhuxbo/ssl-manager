<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Throwable;

class InvoiceLimit extends BaseModel
{
    public const null UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'type',
        'limit_id',
        'amount',
        'limit_before',
        'limit_after',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'limit_before' => 'decimal:2',
        'limit_after' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'limit_id');
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class, 'limit_id');
    }

    /**
     * 模型的"启动"方法
     */
    protected static function boot(): void
    {
        parent::boot();

        // 创建前事件
        static::creating(function ($model) {
            if (bccomp((string) $model->amount, '0.00', 2) === 0) {
                return false;
            }

            DB::beginTransaction();
            try {
                // 检查是否存在重复的发票额度记录
                $exists = self::where(['type' => $model->type, 'limit_id' => $model->limit_id])->exists();
                if ($exists) {
                    throw new Exception('发票额度记录已存在');
                }

                $user = User::where('id', $model->user_id)->lockForUpdate()->first();
                if (! $user) {
                    throw new Exception('用户不存在');
                }

                $model->limit_before = $user->invoice_limit;

                $user->invoice_limit = bcadd((string) $user->invoice_limit, (string) $model->amount, 2);
                $user->save();

                $model->limit_after = $user->invoice_limit;

                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            return true;
        });

        // 禁止更新
        static::updating(function () {
            return false;
        });

        // 禁止删除
        static::deleting(function () {
            return false;
        });
    }
}
