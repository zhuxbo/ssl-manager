<?php

namespace Plugins\Invoice\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasReadOnlyFields;
use App\Models\Traits\HasSnowflakeId;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends BaseModel
{
    use HasReadOnlyFields;
    use HasSnowflakeId;

    protected $table = 'invoices';

    // 只读字段：发票创建后不可修改的字段
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

    protected static function boot(): void
    {
        parent::boot();

        // 创建前事件
        static::creating(function ($model) {
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
}
