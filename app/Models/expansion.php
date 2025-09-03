<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expansion extends Model
{
    protected $table = 'expansions';
    protected $primaryKey = 'espansione';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'espansione',
        'uscita',
        'rotazione',
        'confermato'
    ];

    protected $casts = [
        'espansione' => 'string',
        'uscita' => 'string',
        'rotazione' => 'string',
        'confermato' => 'boolean'
    ];

    /**
     * Get the cards that belong to this expansion
     * Ottiene le carte che appartengono a questa espansione
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function cards(){
        return $this->hasMany(Card::class, 'espansione', 'espansione');
    }
}
