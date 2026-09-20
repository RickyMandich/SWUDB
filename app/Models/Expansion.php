<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expansion extends Model
{
    use HasFactory;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'expansions';
    protected $primaryKey = 'expansion';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = [
        'expansion',
        'legal_date',
        'rotation',
        'confirmed',
        'group_main_expansion',
    ];
    public function cards()
    {
        return $this->hasMany(Card::class);
    }
    public function mainExpansion()
    {
        return $this->belongsTo(Expansion::class, 'group_main_expansion', 'expansion');
    }
    public function subExpansions()
    {
        return $this->hasMany(Expansion::class, 'group_main_expansion', 'expansion');
    }
    public function scopeConfirmed($query)
    {
        return $query->where('confirmed', true);
    }
}
