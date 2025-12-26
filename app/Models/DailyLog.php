<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyLog extends Model
{
    protected $table = 'daily_log';
    
    public $timestamps = false;
    
    protected $fillable = [
        'user_name',
        'project_name',
        'days',
        'hours',
        'content',
        'remark',
    ];
    
    protected $casts = [
        'days' => 'date',
        'hours' => 'decimal:1',
        'create_time' => 'datetime',
        'update_time' => 'datetime',
    ];
    
    // 访问器：确保返回的日期格式为 Y-m-d（避免时区转换问题）
    public function getDaysAttribute($value)
    {
        if ($value) {
            // 如果是 Carbon 实例，格式化为 Y-m-d（不使用时区转换）
            if ($value instanceof \Carbon\Carbon) {
                return $value->format('Y-m-d');
            }
            // 如果是字符串，提取日期部分（处理 ISO 格式）
            if (is_string($value)) {
                return substr($value, 0, 10);
            }
        }
        return $value;
    }
}

