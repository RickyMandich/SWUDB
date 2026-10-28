<?php

it('il catalogo mostra solo gli originali', function () {
    $original = makeCard(['name' => 'Ahsoka Tano']);
    makeCard(['name' => 'Ahsoka Tano', 'original_card_id' => $original->id, 'expansion' => 'SOROP']);

    $response = $this->get(route('cards.index'))->assertOk();
    expect($response->viewData('cards')->total())->toBe(1);
});

it('l\'import di un mazzo converte codici promo swudb nell\'originale', function () {
    $user = User::factory()->create();
    $original = makeCard(['expansion' => 'SOR', 'number' => 10]);
    makeCard(['expansion' => 'SORPR', 'number' => 1, 'original_card_id' => $original->id]);

    $result = app(DeckImporter::class)->fromContent(
        json_encode(['deck' => [['id' => 'SORPR_1', 'count' => 1]]]),
        $user,
        'Mazzo Promo',
        DeckFormat::Premier
    );

    expect($result['deck']->cards->first()->id)->toBe($original->id);
});

it('l\'utente può aggiungere sia l\'originale che la variante alla collezione separatamente', function () {
    $user = User::factory()->create();
    $original = makeCard(['expansion' => 'SOR']);
    $hyperspace = makeCard(['expansion' => 'SOROP', 'original_card_id' => $original->id, 'variant_type' => 'Hyperspace']);

    // La rotta di aggiornamento è PATCH /collezione e non riceve più `variant`
    $this->actingAs($user)
        ->patchJson(route('collection.update'), ['card_id' => $original->id, 'quantity' => 2])
        ->assertOk();

    $this->actingAs($user)
        ->patchJson(route('collection.update'), ['card_id' => $hyperspace->id, 'quantity' => 1])
        ->assertOk();

    expect(CollectionCard::where('user_id', $user->id)->count())->toBe(2);
});

it('la collezione elenca anche le varianti', function () {
    $user = User::factory()->create();
    $original = makeCard(['expansion' => 'SOR']);
    makeCard(['expansion' => 'SOROP', 'original_card_id' => $original->id, 'variant_type' => 'Hyperspace']);

    $response = $this->actingAs($user)->get(route('collection.index'))->assertOk();

    expect($response->viewData('cards')->total())->toBe(2);
});
