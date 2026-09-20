<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardAspect extends Model
{
    protected $table = 'card_aspects';
    protected $primaryKey = 'id';
    protected $keyType = 'int';
    public $incrementing = true;
    protected $fillable = [
        'card_id',
        'aspect_id',
    ];

    public function card()
    {
        return $this->belongsTo(Card::class);
    }

    public function aspect()
    {
        return $this->belongsTo(Aspect::class);
    }
}
