<?php

namespace Plugins\Easy\Models;

use App\Models\BaseModel;
use App\Models\Cert;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Agiso extends BaseModel
{
    protected $fillable = [
        'pay_method',
        'sign',
        'type',
        'data',
        'tid',
        'refund_id',
        'status',
        'product_code',
        'period',
        'price',
        'count',
        'amount',
        'user_id',
        'order_id',
        'recharged',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'price' => 'decimal:2',
        'data' => 'array',
        'count' => 'integer',
        'user_id' => 'integer',
        'order_id' => 'integer',
        'recharged' => 'integer',
        'type' => 'integer',
        'product_code' => 'string',
        'period' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function ($model) {
            $recharged = $model->getOriginal('recharged');

            if ($recharged === 1) {
                return false;
            }

            return true;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function latestCert(): HasOneThrough|Builder|Agiso
    {
        return $this->hasOneThrough(
            Cert::class,
            Order::class,
            'id',
            'id',
            'order_id',
            'latest_cert_id'
        );
    }

    public function product(): HasOneThrough|Builder|Agiso
    {
        return $this->hasOneThrough(
            Product::class,
            Order::class,
            'id',
            'id',
            'order_id',
            'product_id'
        );
    }

    /**
     * 将外部平台代码转换为 pay_method 值
     */
    public static function platformToPayMethod(string $platform): string
    {
        $map = [
            'TbAlds' => 'taobao',
            'PddAlds' => 'pinduoduo',
            'AldsJd' => 'jingdong',
            'AldsDoudian' => 'douyin',
            'gift' => 'gift',
        ];

        return $map[$platform] ?? 'other';
    }
}
