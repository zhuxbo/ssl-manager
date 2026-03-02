<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Notification extends BaseModel
{
    use HasFactory;

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_SENDING = 'sending';

    public const string STATUS_SENT = 'sent';

    public const string STATUS_FAILED = 'failed';

    protected $fillable = [
        'notifiable_type',
        'notifiable_id',
        'template_id',
        'data',
        'read_at',
        'sent_at',
        'status',
    ];

    protected $casts = [
        'data' => 'json',
        'read_at' => 'datetime',
        'sent_at' => 'datetime',
        'status' => 'string',
    ];

    /**
     * 通知接收者
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 通知模板
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }

    /**
     * 标记为已读
     */
    public function markAsRead(): void
    {
        if (! $this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }

    /**
     * 标记为已发送
     */
    public function markAsSent(): void
    {
        $this->status = self::STATUS_SENT;
        $this->sent_at = now();
        $this->save();
    }

    /**
     * 标记为发送失败
     */
    public function markAsFailed(): void
    {
        $this->status = self::STATUS_FAILED;
        $this->save();
    }

    /**
     * 读取记录范围
     */
    public function scopeRead(Builder $query): Builder
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * 未读记录范围
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }
}
