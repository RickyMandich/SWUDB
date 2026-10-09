<?php

namespace App\Models;

use App\Models\Builders\CardBuilder;
use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cards';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted(): void
    {
        static::creating(function (Card $card) {
            $card->id = "{$card->expansion}{$card->number}";
        });
    }

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
        'deploy_text',
        'arena',
        'artist',
        'front_art_path',
        'back_art_path',
        'max_copies',
        'release_date',
    ];

    protected $casts = [
        'release_date' => 'date',
        'unique_card' => 'boolean',
    ];

    protected $appends = [
        'snippet',
    ];

    /**
     * Get the card's snippet attribute for display purposes
     * Ottiene il testo di anteprima della carta per la visualizzazione
     *
     * @return string The formatted snippet with ID, name and title (if present)
     */
    public function getSnippetAttribute()
    {
        $snippet = "$this->expansion-$this->number - ⟡$this->name";
        if (isset($this->title) && strlen($this->title) > 0) {
            return $snippet.', '.strtoupper($this->title);
        }

        return str_replace('⟡', '', $snippet);
    }

    public function expansionModel()
    {
        return $this->belongsTo(Expansion::class, 'expansion', 'expansion');
    }

    public function aspects()
    {
        return $this->belongsToMany(Aspect::class, 'card_aspect', 'card_id', 'aspect_id')
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }

    public function traits()
    {
        return $this->belongsToMany(CardTrait::class, 'card_trait', 'card_id', 'trait_name');
    }

    public function decks()
    {
        return $this->belongsToMany(Deck::class, 'deck_cards', 'card_id', 'deck_id')
            ->using(DeckCard::class)
            ->withPivot('quantity')
            ->withTimestamps();
    }

    /**
     * Secondary aspect of the card (Heroism or Villainy), null if it has none
     * Aspetto secondario della carta (Eroismo o Malvagità), null se non ne ha
     */
    public function secondaryAspect(): ?Aspect
    {
        return $this->aspects->first(
            fn (Aspect $aspect) => in_array($aspect->name, ['Eroismo', 'Malvagità'])
        );
    }

    public function newEloquentBuilder($query): CardBuilder
    {
        return new CardBuilder($query);
    }
}
