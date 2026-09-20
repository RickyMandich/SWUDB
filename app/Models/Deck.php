<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deck extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'format',
        'is_public',
        'assembled',
        'version',
        'previous_version_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cards()
    {
        return $this->belongsToMany(Card::class, 'deck_cards', 'deck_id', 'card_id')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function leader()
    {
        if ($this->format === 'twin_suns') {
            return $this->hasMany(Card::class)
                ->where('type', 'leader');
        }
        return $this->hasOne(Card::class)
            ->where('type', 'leader');
    }

    public function base()
    {
        return $this->hasOne(Card::class)
            ->where('type', 'base');
    }

    public function previousVersion()
    {
        return $this->belongsTo(Deck::class, 'previous_version_id');
    }

    public function nextVersion()
    {
        return $this->hasOne(Deck::class, 'previous_version_id');
    }
}
