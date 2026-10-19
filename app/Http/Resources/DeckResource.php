<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DeckResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'name' => $this->name,
            'format' => $this->format->value,
            'is_public' => (bool) $this->is_public,
            'cards' => $this->cards->map(fn ($c) => [...(new CardResource($c))->resolve(), 'quantity' => $c->pivot->quantity]),
            'version' => $this->version,
        ];
    }
}
