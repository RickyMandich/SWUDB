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
        'confermato',
        'principale'
    ];

    protected $casts = [
        'espansione' => 'string',
        'uscita' => 'string',
        'rotazione' => 'string',
        'confermato' => 'boolean',
        'principale' => 'string'
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

    /**
     * Get the main expansion of this group
     * Ottiene l'espansione principale di questo gruppo
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo|null
     */
    public function mainExpansion()
    {
        if ($this->principale === '0') {
            return null; // Questa è già l'espansione principale
        }
        return $this->belongsTo(Expansion::class, 'principale', 'espansione');
    }

    /**
     * Get all expansions in the same group (including this one)
     * Ottiene tutte le espansioni dello stesso gruppo (inclusa questa)
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function groupExpansions()
    {
        $mainId = $this->principale === '0' ? $this->espansione : $this->principale;
        return $this->hasMany(Expansion::class, 'principale', 'espansione')
                    ->where('principale', $mainId)
                    ->orWhere('espansione', $mainId);
    }

    /**
     * Get child expansions if this is a main expansion
     * Ottiene le espansioni figlie se questa è un'espansione principale
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function childExpansions()
    {
        return $this->hasMany(Expansion::class, 'principale', 'espansione');
    }

    /**
     * Check if this expansion is the main one of its group
     * Verifica se questa espansione è quella principale del suo gruppo
     *
     * @return bool
     */
    public function isMain()
    {
        return $this->principale === '0';
    }
}
