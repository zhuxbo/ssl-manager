<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Agiso extends BaseModel
{
    protected $fillable = [
        'platform',
        'sign',
        'timestamp',
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
        'timestamp' => 'integer',
        'type' => 'integer',
        'product_code' => 'string',
        'period' => 'integer',
    ];

    /**
     * 模型的"启动"方法
     */
    protected static function boot(): void
    {
        parent::boot();

        // 删除前事件
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

    // 通过订单获取最新证书 - 使用hasOneThrough
    public function latestCert(): HasOneThrough|Builder|Agiso
    {
        return $this->hasOneThrough(
            Cert::class,
            Order::class,
            'id', // orders表的主键
            'id', // certs表的主键
            'order_id', // agiso表的外键
            'latest_cert_id' // orders表的外键
        );
    }

    // 通过订单获取产品 - 使用hasOneThrough
    public function product(): HasOneThrough|Builder|Agiso
    {
        return $this->hasOneThrough(
            Product::class,
            Order::class,
            'id', // orders表的主键
            'id', // products表的主键
            'order_id', // agiso表的外键
            'product_id' // orders表的外键
        );
    }

    public static function getPlatform(string $platform): string|array
    {
        $platforms = [
            'TbAlds' => 'taobao',
            'PddAlds' => 'pinduoduo',
            'AldsJd' => 'jingdong',
            'AldsDoudian' => 'douyin',
            'All' => ['taobao', 'pinduoduo', 'jingdong', 'douyin'],
        ];

        return $platforms[$platform] ?? 'other';
    }
}
