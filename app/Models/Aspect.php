<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Aspect extends Model
{
    protected $fillable = [
        'nome',
        'colore',
        'slug',
        'order',
        'primary'
    ];

    /**
     * Get the cards that have this aspect.
     */
    public function cards()
    {
        return $this->belongsToMany(Card::class, 'card_aspect', 'aspect_id', 'card_cid', 'id', 'cid');
    }
}
