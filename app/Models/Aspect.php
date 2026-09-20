<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Aspect extends Model
{
    protected $table = 'aspects';
    protected $primaryKey = 'id';
    protected $keyType = 'int';
    public $incrementing = false;
    protected $fillable = [
        'id',
        'name',
        'slug',
        'color',
        'order',
    ];

    public function cards()
    {
        return $this->belongsToMany(Card::class, 'card_aspect', 'aspect_id', 'cid');
    }
}
