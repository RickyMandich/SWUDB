<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deck extends Model{
    protected $table = 'decks';
    protected $primaryKey = 'id';
    public const CREATED_AT = 'creazione';
    public const UPDATED_AT = 'modifica';
    protected $dateFormat = 'Y-m-d H:i';
    protected $fillable = [
        'id',
        'nome',
        'public',
        'codUtente'
    ];
    protected $casts = [
        'id' => 'integer',
        'nome' => 'string',
        'public' => 'boolean',
        'codUtente' => 'integer'
    ];
}