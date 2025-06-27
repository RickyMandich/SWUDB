<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Awobaz\Compoships\Compoships;

class Composition extends Model{
    use Compoships;

    protected $table = 'compositions';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'espansione',
        'numero',
        'idMazzo',
        'foil',
        'copie'
    ];

    /**
     * Get the deck that owns the composition
     * Ottiene il mazzo proprietario della composizione
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function deck()
    {
        return $this->belongsTo(Deck::class, 'idMazzo');
    }

    /**
     * Get the card for this composition using composite key
     * Ottiene la carta per questa composizione usando chiave composita
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function card()
    {
        return $this->belongsTo(Card::class, ['espansione', 'numero'], ['espansione', 'numero']);
    }
}