<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Composition extends Model{

    protected $table = 'compositions';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'espansione',
        'numero',
        'idMazzo',
        'foil',
        'copie'
    ];
}