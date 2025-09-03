<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expansion extends Model
{
    protected $table = 'expansions';
    protected $primaryKey = 'espansione';
    public $incrementing = false;
    protected $keyType = 'string';
    public const CREATED_AT = null;
    public const UPDATED_AT = null;

    protected $fillable = [
        'espansione',
        'uscita'
    ];

    protected $casts = [
        'espansione' => 'string',
        'uscita' => 'string'
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
