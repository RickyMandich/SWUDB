<?php
namespace App\Models;

use App\Events\MessageCreated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Card extends Model{
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
        'aspettoPrimario',
        'aspettoSecondario',
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
        'uscita',
        'frontArt',
        'backArt',
        'maxCopie'
    ];
    protected $appends = [
        'id',
        'snippet'
    ];
    public function getIdAttribute(){
        return "$this->espansione-$this->numero";
    }
    public function getSnippetAttribute(){
        return "$this->id - ".$this->nome.(strlen($this->titolo) > 0 ? ", ". strtoupper($this->titolo) : "");
    }
    protected $casts = [
        'cid' => 'string',
        'espansione' => 'string',
        'numero' => 'integer',
        'aspettoPrimario' => 'string',
        'aspettoSecondario' => 'string',
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
        'artista' => 'string',
        'uscita' => 'datetime:Y-m-d H:i'
    ];
    public function getFillable(){
        return $this->fillable;
    }

    /**
     * Invalida la cache delle carte quando viene salvata una carta
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
     * Metodo statico per invalidare manualmente la cache delle carte
     */
    public static function clearCache()
    {
        // Cache del popup
        Cache::forget('cards_popup_basic');

        // Cache dei filtri
        Cache::forget('cards_filter_espansioni');
        Cache::forget('cards_filter_tipi');
        Cache::forget('cards_filter_aspetti_primari');
        Cache::forget('cards_filter_aspetti_secondari');
        Cache::forget('cards_filter_rarita');
        Cache::forget('cards_filter_arene');
        Cache::forget('cards_filter_artisti');
        Cache::forget('cards_filter_max_values');
    }
}