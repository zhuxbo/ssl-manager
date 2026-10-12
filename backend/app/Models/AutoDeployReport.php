<?php

namespace App\Models;

use App\Models\Traits\HasSnowflakeId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int|string|null $report_count 按订单聚合查询的失败记录数
 * @property-read int|string|null $client_failure_count 按订单聚合查询的客户端失败记录数
 */
class AutoDeployReport extends BaseModel
{
    use HasSnowflakeId;

    const null UPDATED_AT = null;

    protected $fillable = [
        'order_id',
        'cert_id',
        'status',
        'deployed_at',
        'ip',
        'message',
    ];

    protected $casts = [
        'deployed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function cert(): BelongsTo
    {
        return $this->belongsTo(Cert::class);
    }
}
