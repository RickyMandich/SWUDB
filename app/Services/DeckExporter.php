<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Deck;
use Illuminate\Support\Collection;

class DeckExporter
{
    /**
     * Plain-text export: sections Leaders / Base / Deck, one "{copies} | {name}[ | {title}]" line per card
     * Export in testo: sezioni Leaders / Base / Deck, una riga "{copie} | {nome}[ | {titolo}]" per carta
     */
    public function toText(Deck $deck): string
    {
        [$leaders, $bases, $main] = $this->split($deck);

        $line = fn (Card $card): string => $card->pivot->quantity.' | '.$card->name.($card->title ? ' | '.$card->title : '');

        $sections = [];
        foreach (['Leaders' => $leaders, 'Base' => $bases, 'Deck' => $main] as $title => $cards) {
            if ($cards->isNotEmpty()) {
                $sections[] = $title."\n".$cards->map($line)->implode("\n");
            }
        }

        return implode("\n\n", $sections)."\n";
    }

    /**
     * JSON export in the official format (ids are "{EXPANSION}_{number}")
     * Export JSON nel formato ufficiale (gli id sono "{ESPANSIONE}_{numero}")
     */
    public function toJson(Deck $deck): string
    {
        [$leaders, $bases, $main] = $this->split($deck);

        $entry = fn (Card $card): array => [
            'id' => "{$card->expansion}_{$card->number}",
            'count' => (int) $card->pivot->quantity,
        ];

        $data = ['metadata' => ['name' => $deck->name, 'author' => $deck->user->name]];

        if ($leaders->isNotEmpty()) {
            $data['leader'] = $entry($leaders[0]);
        }
        if ($leaders->count() > 1) {
            $data['secondleader'] = $entry($leaders[1]); // Twin Suns
        }
        if ($bases->isNotEmpty()) {
            $data['base'] = $entry($bases[0]);
        }
        $data['deck'] = $main->map($entry)->all();

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Leaders, bases and the rest of the deck, the rest sorted by cost then name
     * Leader, basi e resto del mazzo, il resto ordinato per costo e poi per nome
     *
     * @return array{0: Collection<int, Card>, 1: Collection<int, Card>, 2: Collection<int, Card>}
     */
    private function split(Deck $deck): array
    {
        $deck->loadMissing('cards', 'user');

        $cards = $deck->cards->sortBy([['cost', 'asc'], ['name', 'asc']]);

        return [
            $cards->where('type', 'Leader')->values(),
            $cards->where('type', 'Base')->values(),
            $cards->reject(fn (Card $card) => in_array($card->type, ['Leader', 'Base']))->values(),
        ];
    }
}
