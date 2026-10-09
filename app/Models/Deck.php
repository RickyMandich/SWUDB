<?php

namespace App\Models;

use App\Enums\DeckFormat;
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

    protected $casts = [
        'format' => DeckFormat::class,
        'is_public' => 'boolean',
        'assembled' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cards()
    {
        return $this->belongsToMany(Card::class, 'deck_cards', 'deck_id', 'card_id')
            ->using(DeckCard::class)
            ->withPivot('quantity')
            ->withTimestamps();
    }

    /**
     * Leader cards of the deck (1 in Premier/Eternal, 2 in Twin Suns); the count is enforced by the format validator
     * Carte leader del mazzo (1 in Premier/Eternal, 2 in Twin Suns); il conteggio lo controlla il validator del formato
     */
    public function leaders()
    {
        return $this->belongsToMany(Card::class, 'deck_cards', 'deck_id', 'card_id')
            ->using(DeckCard::class)
            ->withPivot('quantity')
            ->where('cards.type', 'Leader');
    }

    /**
     * Base card of the deck
     * Carta base del mazzo
     */
    public function baseCard()
    {
        return $this->belongsToMany(Card::class, 'deck_cards', 'deck_id', 'card_id')
            ->using(DeckCard::class)
            ->withPivot('quantity')
            ->where('cards.type', 'Base');
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
