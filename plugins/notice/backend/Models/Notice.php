<?php

namespace Plugins\Notice\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Plugins\Notice\Factories\NoticeFactory;

class Notice extends Model
{
    use HasFactory;

    protected $table = 'notice_notices';

    protected $fillable = [
        'title',
        'content',
        'type',
        'is_active',
        'sort',
    ];

    protected static function newFactory(): NoticeFactory
    {
        return NoticeFactory::new();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
