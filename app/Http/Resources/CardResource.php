<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CardResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'cid' => $this->cid,
            'name' => $this->name,
            'title' => $this->title,
            'type' => $this->type,
            'rarity' => $this->rarity,
            'cost' => $this->cost,
            'power' => $this->power,
            'health' => $this->health,
            'text' => $this->text,
            'expansion' => $this->expansion,
            'number' => $this->number,
            'aspects' => $this->aspects->pluck('name'),
            'traits' => $this->traits->pluck('name'),
            'front_image' => $this->front_art_path ? asset('storage/'.$this->front_art_path) : null,
            'back_image' => $this->back_art_path ? asset('storage/'.$this->back_art_path) : null,
            'variant_type' => $this->variant_type,
            'variant_code' => $this->variant_code,
            'original' => $this->whenLoaded('originalCard', fn () => $this->originalCard ? [
                'cid' => $this->originalCard->cid,
                'expansion' => $this->originalCard->expansion,
                'number' => $this->originalCard->number,
            ] : null),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(fn ($variant) => [
                'cid' => $variant->cid,
                'expansion' => $variant->expansion,
                'number' => $variant->number,
                'variant_type' => $variant->variant_type,
                'front_image' => $variant->front_art_path ? asset('storage/'.$variant->front_art_path) : null,
                'back_image' => $variant->back_art_path ? asset('storage/'.$variant->back_art_path) : null,
            ])),
        ];
    }
}
