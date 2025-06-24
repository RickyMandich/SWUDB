<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deck extends Model{
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
}