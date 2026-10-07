<?php

namespace App\Services;

use App\Models\Card;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class CardSearch
{
    public function getBounds(): array
    {
        return Cache::remember('cards_bounds', 3600, function () {
            $boundsRaw = Card::query()->toBase()->selectRaw('
                MIN(cost) as cost_min, MAX(cost) as cost_max,
                MIN(health) as health_min, MAX(health) as health_max,
                MIN(power) as power_min, MAX(power) as power_max
            ')->first();

            return [
                'cost' => [(int) ($boundsRaw->cost_min ?? 0), (int) ($boundsRaw->cost_max ?? 0)],
                'health' => [(int) ($boundsRaw->health_min ?? 0), (int) ($boundsRaw->health_max ?? 0)],
                'power' => [(int) ($boundsRaw->power_min ?? 0), (int) ($boundsRaw->power_max ?? 0)],
            ];
        });
    }

    /**
     * Applies GET filters onto a Card query, shared between the /carte page and the public API (Fase 11)
     * Applica i filtri GET su una query di Card, condivisa tra la pagina /carte e l'API pubblica (Fase 11)
     */
    public function apply(Builder $query, array $filters): Builder
    {
        $bounds = $this->getBounds();

        return $query
            ->when($filters['nome'] ?? null, fn ($q, $nome) => $q->where('name', 'like', "%{$nome}%"))
            ->when($filters['espansioni'] ?? null, fn ($q, $exp) => $q->whereIn('expansion', (array) $exp))
            ->when($filters['tipi'] ?? null, fn ($q, $tipi) => $q->whereIn('type', (array) $tipi))
            ->when($filters['aspetti'] ?? null, function ($q, $aspetti) {
                foreach ((array) $aspetti as $aspettoId) {
                    $q->whereHas('aspects', fn ($q2) => $q2->where('aspects.id', $aspettoId));
                }
            })
            ->when($filters['tratti'] ?? null, function ($q, $tratti) {
                foreach ((array) $tratti as $trattoNome) {
                    $q->whereHas('traits', fn ($q2) => $q2->where('traits.name', $trattoNome));
                }
            })
            ->when(
                isset($filters['costo_min']) && $filters['costo_min'] !== '' && (int) $filters['costo_min'] > $bounds['cost'][0],
                fn ($q) => $q->where('cost', '>=', (int) $filters['costo_min'])
            )
            ->when(
                isset($filters['costo_max']) && $filters['costo_max'] !== '' && (int) $filters['costo_max'] < $bounds['cost'][1],
                fn ($q) => $q->where('cost', '<=', (int) $filters['costo_max'])
            )
            ->when(
                isset($filters['vita_min']) && $filters['vita_min'] !== '' && (int) $filters['vita_min'] > $bounds['health'][0],
                fn ($q) => $q->where('health', '>=', (int) $filters['vita_min'])
            )
            ->when(
                isset($filters['vita_max']) && $filters['vita_max'] !== '' && (int) $filters['vita_max'] < $bounds['health'][1],
                fn ($q) => $q->where('health', '<=', (int) $filters['vita_max'])
            )
            ->when(
                isset($filters['potenza_min']) && $filters['potenza_min'] !== '' && (int) $filters['potenza_min'] > $bounds['power'][0],
                fn ($q) => $q->where('power', '>=', (int) $filters['potenza_min'])
            )
            ->when(
                isset($filters['potenza_max']) && $filters['potenza_max'] !== '' && (int) $filters['potenza_max'] < $bounds['power'][1],
                fn ($q) => $q->where('power', '<=', (int) $filters['potenza_max'])
            )
            ->when(
                isset($filters['unique_card']) && $filters['unique_card'] !== '',
                fn ($q) => $q->where('unique_card', (bool) $filters['unique_card'])
            );
    }
}
