<?php

namespace App\Models;

use App\Models\Traits\HasReadOnlyFields;
use App\Models\Traits\HasSnowflakeId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Throwable;

class Invoice extends BaseModel
{
    use HasReadOnlyFields;
    use HasSnowflakeId;

    // 只读字段
    protected array $readOnlyFields = ['user_id', 'amount', 'organization', 'taxation'];

    protected $fillable = [
        'user_id',
        'amount',
        'organization',
        'taxation',
        'remark',
        'email',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'status' => 'integer',
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
            if ($model->status == 1) {
                self::createInvoiceLimit($model, 'issue');
            }

            if ($model->status == 2) {
                return false;
            }

            return true;
        });

        // 更新前事件
        static::updating(function ($model) {
            $originStatus = $model->getOriginal('status');
            if ($originStatus === 2) {
                return false;
            }

            if ($originStatus === 0 && $model->status === 2) {
                return false;
            }

            if ($originStatus === 1 && $model->status === 0) {
                return false;
            }

            if ($originStatus === 0 && $model->status === 1) {
                self::createInvoiceLimit($model, 'issue');
            }

            if ($originStatus === 1 && $model->status === 2) {
                self::createInvoiceLimit($model, 'void');
            }

            return true;
        });

        // 删除前事件
        static::deleting(function ($model) {
            if ($model->status === 0) {
                return true;
            }

            return false;
        });
    }

    /**
     * 创建发票额度
     *
     * @throws Throwable
     */
    protected static function createInvoiceLimit($model, $type): void
    {
        DB::beginTransaction();
        try {
            $invoiceLimit['limit_id'] = $model->id;
            $invoiceLimit['user_id'] = $model->user_id;
            $invoiceLimit['type'] = $type;

            $amount = abs(floatval($model->amount));
            $model->amount = number_format($amount, 2, '.', '');

            if ($invoiceLimit['type'] == 'void') {
                $invoiceLimit['amount'] = $model->amount;
            } else {
                $invoiceLimit['amount'] = '-'.$model->amount;
            }

            InvoiceLimit::create($invoiceLimit);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
