<?php

namespace App\Models\Builders;

use Illuminate\Database\Eloquent\Builder;

class CardBuilder extends Builder
{
    /**
     * Applica l'ordinamento di default solo quando si recuperano i modelli
     * e la query non ha già un proprio order by.
     */
    public function get($columns = ['*'])
    {
        if (empty($this->query->orders) && empty($this->query->unionOrders)) {
            $this->applyDefaultOrder();
        }

        return parent::get($columns);
    }

    protected function applyDefaultOrder(): void
    {
        // 1. Tipo generico: Leader, poi Base, poi tutto il resto
        $this->orderByRaw("CASE cards.type WHEN 'Leader' THEN 0 WHEN 'Base' THEN 1 ELSE 2 END");

        // 2. Aspetti: stringa degli `order` (zero-padded) nella sequenza in cui compaiono
        //    sulla carta (position, poi created_at e order come tie-breaker per i dati vecchi).
        //    Le carte senza aspetti (NULL) vanno dopo quelle che ne hanno.
        $aspects = $this->aspectOrderKey();

        $this->orderByRaw("($aspects) IS NULL")
            ->orderByRaw($aspects);

        // 3. Tipo specifico: Unità, Miglioria, Evento (gli altri in fondo)
        $this->orderByRaw("CASE cards.type WHEN 'Unità' THEN 0 WHEN 'Miglioria' THEN 1 WHEN 'Evento' THEN 2 ELSE 3 END");

        // 4. Costo crescente
        $this->orderBy('cards.cost');

        // 5. Nome in ordine alfabetico
        $this->orderBy('cards.name');

        // 6. Data legale dell'espansione
        $this->orderByRaw('(SELECT e.legal_date FROM expansions e WHERE e.expansion = cards.expansion)');

        // 7. Numero della carta
        $this->orderBy('cards.number');

        // Tie-breaker finale per rendere stabile la paginazione
        $this->orderBy('cards.id');
    }

    /**
     * SQL expression that builds the aspects sort key of a card (zero-padded `order`s, NULL if none).
     * Espressione SQL della chiave di ordinamento degli aspetti: MariaDB per l'app, SQLite per i test.
     *
     * @return string Raw SQL, a correlated scalar subquery on `cards.id`
     */
    protected function aspectOrderKey(): string
    {
        if ($this->query->getConnection()->getDriverName() === 'sqlite') {
            // SQLite non ha LPAD e non accetta ORDER BY/SEPARATOR dentro GROUP_CONCAT: printf al posto di LPAD,
            // nessun ordine interno garantito (basta per i test, che non verificano l'ordine dei multi-aspetto).
            return <<<'SQL'
            (SELECT GROUP_CONCAT(printf('%04d', a."order"), '')
                FROM card_aspect ca
                JOIN aspects a ON a.id = ca.aspect_id
                WHERE ca.card_id = cards.id)
            SQL;
        }

        return <<<'SQL'
        (SELECT GROUP_CONCAT(
                LPAD(a.`order`, 4, '0')
                ORDER BY ca.position, ca.created_at, a.`order`
                SEPARATOR ''
            )
            FROM card_aspect ca
            JOIN aspects a ON a.id = ca.aspect_id
            WHERE ca.card_id = cards.id)
        SQL;
    }

    public function withDefaultOrder(): static
    {
        $this->applyDefaultOrder();

        return $this;
    }
}
