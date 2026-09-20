<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardTrait extends Model
{
    protected $table = 'traits';
    protected $primaryKey = 'name';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = [
        'name',
    ];

    public function cards()
    {
        return $this->belongsToMany(Card::class, 'card_trait', 'trait_name', 'cid');
    }
}
