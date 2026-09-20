<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemError extends Model
{
    protected $table = 'system_errors';
    protected $fillable = [
        'source',
        'message',
        'stack_trace',
        'context',
        'status',
        'resolved_at',
    ];
    protected $casts = [
        'context' => 'array',
        'resolved_at' => 'datetime',
    ];
}
