<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Awobaz\Compoships\Compoships;

class Deck extends Model{
    use Compoships;
    protected $table = 'decks';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'id',
        'nome',
        'public',
        'codUtente',
        'versione'
    ];
    protected $casts = [
        'id' => 'integer',
        'nome' => 'string',
        'public' => 'boolean',
        'codUtente' => 'integer',
        'versione' => 'integer'
    ];

    /**
     * Increment deck version when deck is modified
     * Incrementa la versione del mazzo quando viene modificato
     *
     * @return void
     */
    public function incrementVersion()
    {
        $this->versione = ($this->versione ?? 1) + 1;
        $this->save();
    }

    /**
     * Get formatted version string
     * Ottiene la stringa della versione formattata
     *
     * @return string
     */
    public function getVersionString()
    {
        return 'v' . ($this->versione ?? 1);
    }

    /**
     * Get the user that owns the deck
     * Ottiene l'utente proprietario del mazzo
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'codUtente');
    }

    /**
     * Get the compositions for the deck
     * Ottiene le composizioni del mazzo
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function compositions()
    {
        return $this->hasMany(Composition::class, 'idMazzo');
    }



    /**
     * Get cards with their composition data using composite keys
     * Ottiene le carte con i dati delle composizioni usando chiavi composite
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function cardsWithCompositions()
    {
        return $this->hasMany(Composition::class, 'idMazzo')
                    ->with('card');
    }
}