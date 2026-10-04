<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class CardSearch
{
    /**
     * Applies GET filters onto a Card query, shared between the /carte page and the public API (Fase 11)
     * Applica i filtri GET su una query di Card, condivisa tra la pagina /carte e l'API pubblica (Fase 11)
     */
    public function apply(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['nome'] ?? null, fn ($q, $nome) => $q->where('name', 'like', "%{$nome}%"))
            ->when($filters['espansione'] ?? null, fn ($q, $exp) => $q->where('expansion', $exp))
            ->when($filters['tipo'] ?? null, fn ($q, $tipo) => $q->where('type', $tipo))
            ->when($filters['costo'] ?? null, fn ($q, $costo) => $q->where('cost', $costo))
            ->when($filters['aspetto'] ?? null, fn ($q, $id) => $q->whereHas('aspects', fn ($q2) => $q2->where('aspects.id', $id)))
            ->when($filters['tratto'] ?? null, fn ($q, $nome) => $q->whereHas('traits', fn ($q2) => $q2->where('traits.name', $nome)))
            ->when($filters['unique_card'] ?? null, fn ($q) => $q->where('unique_card', true));
    }
}
