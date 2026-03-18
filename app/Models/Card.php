<?php
namespace App\Models;

use App\Events\MessageCreated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Awobaz\Compoships\Compoships;

class Card extends Model{
    use Compoships;
    protected $table = 'cards';
    public $incrementing = false;
    protected $primaryKey = 'cid';
    public const CREATED_AT = null;
    public const UPDATED_AT = null;
    protected $dateFormat = 'Y-m-d H:i';
    protected $fillable = [
        'cid',
        'espansione',
        'numero',
        'unica',
        'nome',
        'titolo',
        'tipo',
        'rarita',
        'costo',
        'vita',
        'potenza',
        'descrizione',
        'tratti',
        'arena',
        'artista',
        'frontArt',
        'backArt',
        'maxCopie'
    ];
    protected $appends = [
        'id',
        'snippet',
        'uscita'
    ];
    /**
     * Get the card's formatted ID attribute (expansion-number format)
     * Ottiene l'ID formattato della carta nel formato espansione-numero
     *
     * @return string The formatted card ID
     */
    public function getIdAttribute(){
        return "$this->espansione-$this->numero";
    }

    /**
     * Get the card's snippet attribute for display purposes
     * Ottiene il testo di anteprima della carta per la visualizzazione
     *
     * @return string The formatted snippet with ID, name and title (if present)
     */
    public function getSnippetAttribute(){
        return "$this->id - ".$this->nome.(strlen($this->titolo) > 0 ? ", ". strtoupper($this->titolo) : "");
    }

    /**
     * Get the card's release date from the expansion
     * Ottiene la data di uscita della carta dall'espansione
     *
     * @return string|null The release date from the expansion
     */
    public function getUscitaAttribute(){
        return $this->expansion?->uscita;
    }
    protected $casts = [
        'cid' => 'string',
        'espansione' => 'string',
        'numero' => 'integer',
        'unica' => 'boolean',
        'nome' => 'string',
        'titolo' => 'string',
        'tipo' => 'string',
        'rarita' => 'string',
        'costo' => 'integer',
        'vita' => 'integer',
        'potenza' => 'integer',
        'descrizione' => 'string',
        'tratti' => 'string',
        'arena' => 'string',
        'artista' => 'string'
    ];
    /**
     * Get the fillable attributes array
     * Ottiene l'array degli attributi che possono essere assegnati in massa
     *
     * @return array The fillable attributes
     */
    public function getFillable(){
        return $this->fillable;
    }

    /**
     * Boot the model and register event listeners for cache invalidation
     * Avvia il modello e registra i listener per invalidare la cache automaticamente
     *
     * This method automatically clears the cache when cards are saved or deleted
     * to ensure data consistency across the application.
     */
    protected static function booted()
    {
        static::saved(function () {
            self::clearCache();
        });

        static::deleted(function () {
            self::clearCache();
        });
    }

    /**
     * Static method to manually invalidate all card-related cache entries
     * Metodo statico per invalidare manualmente tutte le cache relative alle carte
     *
     * This method clears all cached data related to cards including popup data
     * and filter options. Use this when you need to force cache refresh.
     *
     * @return void
     */
    public static function clearCache()
    {
        // Cache del popup
        Cache::forget('cards_popup_basic');

        // Cache dei filtri
        Cache::forget('cards_filter_espansioni');
        Cache::forget('cards_filter_tipi');
        Cache::forget('cards_filter_aspetti');
        Cache::forget('cards_filter_rarita');
        Cache::forget('cards_filter_rotazioni');
        Cache::forget('cards_filter_arene');
        Cache::forget('cards_filter_artisti');
        Cache::forget('cards_filter_max_values');
    }

    /**
     * Get the expansion that this card belongs to
     * Ottiene l'espansione a cui appartiene questa carta
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function expansion()
    {
        return $this->belongsTo(Expansion::class, 'espansione', 'espansione');
    }

    /**
     * Get the compositions for this card
     * Ottiene le composizioni per questa carta
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function compositions()
    {
        return $this->hasMany(Composition::class, ['espansione', 'numero'], ['espansione', 'numero']);
    }

    /**
     * Get the decks that contain this card through compositions
     * Ottiene i mazzi che contengono questa carta attraverso le composizioni
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function decks()
    {
        return $this->compositions()->with('deck');
    }

    /**
     * Get the aspects for this card.
     * Ottiene gli aspetti per questa carta.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function aspects()
    {
        return $this->belongsToMany(Aspect::class, 'card_aspect', 'card_cid', 'aspect_id', 'cid', 'id')
                    ->withPivot('sort_order')
                    ->orderByPivot('sort_order', 'asc');
    }
}