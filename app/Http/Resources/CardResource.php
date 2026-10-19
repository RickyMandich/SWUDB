<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CardResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            // 'id' (espansione+numero) volutamente escluso: 'cid' è la chiave pubblica stabile dell'API ufficiale
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
        ];
    }
}
