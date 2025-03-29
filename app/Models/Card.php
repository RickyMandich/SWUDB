<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Card extends Model{
    protected $table = 'cards';
    public $incrementing = false;
    protected $primaryKey = 'cid';
    public const CREATED_AT = 'creazione';
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
        'uscita'
    ];
    protected $appends = [
        'id',
        'snippet'
    ];
    public function getIdAttribute(){
        return "$this->espansione-$this->numero";
    }
    public function getSnippetAttribute(){
        return "$this->id - ".$this->nome.((strlen($this->titolo) > 0 ? ", ". strtoupper($this->titolo) : ""));
    }
    protected $hidden = [
        'creazione'
    ];
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
}