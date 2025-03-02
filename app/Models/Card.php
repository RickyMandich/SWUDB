<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    /**
     * La tabella associata al model.
     *
     * @var string
     */
    protected $table = 'Cards';

    /**
     * Indica se il model deve usare i timestamp.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * La chiave primaria della tabella.
     *
     * @var array
     */
    protected $primaryKey = ['espansione', 'numero'];

    /**
     * Indica se la chiave primaria è incrementale.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Il tipo di dato della chiave primaria.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Gli attributi che possono essere assegnati in massa.
     *
     * @var array
     */
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

    /**
     * Gli attributi che devono essere convertiti.
     *
     * @var array
     */
    protected $casts = [
        'numero' => 'integer',
        'unica' => 'boolean',
        'costo' => 'integer',
        'vita' => 'integer',
        'potenza' => 'integer'
    ];
}