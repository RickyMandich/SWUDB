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
        'codUtente'
    ];
    protected $casts = [
        'id' => 'integer',
        'nome' => 'string',
        'public' => 'boolean',
        'codUtente' => 'integer'
    ];
}