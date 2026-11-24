<?php

// /nuove-uscite: `release_date` è la data di inserimento nel sito, il filtro `since` è in formato gg/mm/aaaa
// e la pagina mostra tutte le carte (varianti comprese), a differenza del catalogo.

it('risponde anche senza nessuna carta e senza il parametro since', function () {
    $this->get(route('cards.new-releases'))->assertOk();
});

it('senza since mostra solo le carte inserite nell\'ultima data', function () {
    makeCard(['release_date' => '2026-01-10']);
    makeCard(['release_date' => '2026-02-20']);

    $response = $this->get(route('cards.new-releases'))->assertOk();

    expect($response->viewData('cards')->total())->toBe(1)
        ->and($response->viewData('since'))->toBe('20/02/2026');
});

it('since filtra le carte inserite da quel giorno in poi', function () {
    makeCard(['release_date' => '2026-01-10']);
    makeCard(['release_date' => '2026-02-20']);
    makeCard(['release_date' => '2026-03-05']);

    $response = $this->get(route('cards.new-releases', ['since' => '15/01/2026']))->assertOk();

    expect($response->viewData('cards')->total())->toBe(2)
        ->and($response->viewData('dates'))->toBe(2);
});

it('pagina le carte inserite, 24 per pagina', function () {
    foreach (range(1, 30) as $i) {
        makeCard(['release_date' => '2026-02-20']);
    }

    $first = $this->get(route('cards.new-releases', ['since' => '20/02/2026']))->assertOk();
    $second = $this->get(route('cards.new-releases', ['since' => '20/02/2026', 'page' => 2]))->assertOk();

    expect($first->viewData('cards')->count())->toBe(24)
        ->and($first->viewData('cards')->total())->toBe(30)
        ->and($second->viewData('cards')->count())->toBe(6);
});

it('mostra anche le varianti, non solo gli originali', function () {
    $original = makeCard(['release_date' => '2026-02-20']);
    makeCard([
        'release_date' => '2026-02-20',
        'expansion' => 'SOROP',
        'original_card_id' => $original->id,
        'variant_type' => 'Hyperspace',
    ]);

    $response = $this->get(route('cards.new-releases', ['since' => '20/02/2026']))->assertOk();

    expect($response->viewData('cards')->total())->toBe(2);
});

it('rifiuta un since in formato sbagliato', function () {
    $this->get(route('cards.new-releases', ['since' => '2026-02-20']))
        ->assertSessionHasErrors('since');
});
