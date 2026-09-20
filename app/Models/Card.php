<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cards';
    protected $primaryKey = 'cid';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = [
        'expansion',
        'number',
        'cid',
        'unique_card',
        'name',
        'title',
        'type',
        'rarity',
        'cost',
        'health',
        'power',
        'text',
        'arena',
        'artist',
        'front_art_path',
        'back_art_path',
        'max_copies',
        'release_date',
    ];

    public function expansionModel()
    {
        return $this->belongsTo(Expansion::class, 'expansion', 'expansion');
    }

    public function aspects()
    {
        return $this->belongsToMany(Aspect::class, 'card_aspect', 'cid', 'aspect_id');
    }

    public function traits()
    {
        return $this->belongsToMany(CardTrait::class, 'card_trait', 'cid', 'trait_name');
    }

    public function decks()
    {
        return $this->belongsToMany(Deck::class, 'deck_cards', 'cid', 'deck_id', 'cid', 'id')
            ->using(DeckCard::class)
            ->withPivot('quantity')
            ->withTimestamps();
    }
}
