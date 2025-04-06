<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Composition extends Model{
    protected $table = 'compositions';
    protected $primaryKey = ['espansione', 'numero'];
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'espansione',
        'numero',
        'idMazzo',
        'foil',
    ];
}
