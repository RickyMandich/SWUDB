<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollectionCard extends Model
{
    protected $fillable = ['user_id', 'card_id', 'variant', 'quantity'];

    public function card()
    {
        return $this->belongsTo(Card::class, 'card_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
