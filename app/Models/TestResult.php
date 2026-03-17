<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestResult extends Model
{
    protected $fillable = [
        'test_name',
        'status',
        'output',
        'duration',
        'run_id',
    ];

    /**
     * Scope for failed tests
     */
    public function scopeFailed($query)
    {
        return $query->where('status', false);
    }
}
