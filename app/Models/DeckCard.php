<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class DeckCard extends Pivot
{
    protected $table = 'deck_cards';

    public $incrementing = false;

    protected $fillable = [
        'deck_id',
        'cid',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function card()
    {
        return $this->belongsTo(Card::class, 'cid', 'cid');
    }

    public function deck()
    {
        return $this->belongsTo(Deck::class);
    }
}