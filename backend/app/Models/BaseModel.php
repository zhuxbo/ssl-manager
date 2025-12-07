<?php

namespace App\Models;

use App\Models\Traits\HasLocalTimezone;
use Illuminate\Database\Eloquent\Model;

/**
 * 基础模型类
 *
 * 所有模型都应该继承此类，以便统一管理共享的功能
 */
abstract class BaseModel extends Model
{
    use HasLocalTimezone;
}
