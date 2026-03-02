<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Throwable;

class Transaction extends BaseModel
{
    use HasFactory;

    // Laravel 不更新时间戳
    public const null UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'type',
        'transaction_id',
        'amount',
        'standard_count',
        'wildcard_count',
        'balance_before',
        'balance_after',
        'remark',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
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
                // 交易类型不为order时，检查是否存在重复的交易记录
                if ($model->type !== 'order') {
                    $exists = self::where(['type' => $model->type, 'transaction_id' => $model->transaction_id])->exists();
                    if ($exists) {
                        throw new Exception('交易记录已存在');
                    }
                }

                $user = User::where('id', $model->user_id)->lockForUpdate()->first();
                if (! $user) {
                    throw new Exception('用户不存在');
                }

                $model->balance_before = $user->balance;

                $user->balance = bcadd((string) $user->balance, (string) $model->amount, 2);
                $user->save();

                $model->balance_after = $user->balance;

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
