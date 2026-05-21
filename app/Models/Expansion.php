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
        if ($this->principale === '0' || $this->principale === '-1') {
            return null; // Questa è un'espansione principale o standalone
        }
        return $this->belongsTo(Expansion::class, 'principale', 'espansione');
    }

    /**
     * Get all expansions in the same group (including this one)
     * Ottiene tutte le espansioni dello stesso gruppo (inclusa questa)
     *
     * @return \Illuminate\Support\Collection|null
     */
    public function groupExpansions()
    {
        // Se è standalone, non ha gruppo
        if ($this->principale === '-1') {
            return null;
        }

        // Determina l'ID dell'espansione principale del gruppo
        $mainId = $this->principale === '0' ? $this->espansione : $this->principale;

        // Ottieni tutte le espansioni del gruppo (principale + dipendenti)
        return Expansion::where('espansione', $mainId)
                        ->orWhere('principale', $mainId)
                        ->get();
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

    /**
     * Check if this expansion is standalone (neither main nor dependent)
     * Verifica se questa espansione è standalone (né principale né dipendente)
     *
     * @return bool
     */
    public function isStandalone()
    {
        return $this->principale === '-1';
    }

    /**
     * Check if this expansion is dependent on another
     * Verifica se questa espansione è dipendente da un'altra
     *
     * @return bool
     */
    public function isDependent()
    {
        return $this->principale !== '0' && $this->principale !== '-1';
    }

    /**
     * Scope to get only main expansions
     * Scope per ottenere solo le espansioni principali
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeMain($query)
    {
        return $query->where('principale', '0');
    }

    /**
     * Scope to get only standalone expansions
     * Scope per ottenere solo le espansioni standalone
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeStandalone($query)
    {
        return $query->where('principale', '-1');
    }

    /**
     * Scope to get only dependent expansions
     * Scope per ottenere solo le espansioni dipendenti
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDependent($query)
    {
        return $query->where('principale', '!=', '0')
                     ->where('principale', '!=', '-1');
    }

    /**
     * Scope to get main and standalone expansions (non-dependent)
     * Scope per ottenere espansioni principali e standalone (non dipendenti)
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeIndependent($query)
    {
        return $query->whereIn('principale', ['0', '-1']);
    }
}
