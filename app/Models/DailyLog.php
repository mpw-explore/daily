<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyLog extends Model
{
    protected $table = 'daily_log';
    
    public $timestamps = false;
    
    protected $dates = ['days', 'create_time', 'update_time'];
    
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
        'hours' => 'decimal:2',
        'create_time' => 'datetime',
        'update_time' => 'datetime',
    ];
}

