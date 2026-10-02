<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemError extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_IGNORED = 'ignored';

    public const STATUS_RESOLVED = 'resolved';

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

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_OPEN => '#ef4444',
            self::STATUS_RESOLVED => '#22c55e',
            default => '#6b7280',
        };
    }
}
