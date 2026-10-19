<?php

use App\Models\Aspect;
use App\Models\CardTrait;
use App\Models\User;

test('card detail returns the card resource', function () {
    $aspect = Aspect::create(['name' => 'Vigilanza', 'slug' => 'vigilance', 'order' => 1, 'color' => '#4073d4']);
    $trait = CardTrait::create(['name' => 'Ribelle']);
    $card = makeCard([
        'name' => 'Luke Skywalker',
        'title' => 'Jedi Knight',
        'front_art_path' => 'cards/SOR/1-front.jpg',
    ]);
    $card->aspects()->attach($aspect->id);
    $card->traits()->attach($trait->name);

    $response = $this->getJson("/api/cards/{$card->expansion}/{$card->number}")
        ->assertOk()
        ->assertJsonPath('data.cid', $card->cid)
        ->assertJsonPath('data.name', 'Luke Skywalker')
        ->assertJsonPath('data.title', 'Jedi Knight')
        ->assertJsonPath('data.expansion', $card->expansion)
        ->assertJsonPath('data.number', $card->number)
        ->assertJsonPath('data.aspects', ['Vigilanza'])
        ->assertJsonPath('data.traits', ['Ribelle'])
        ->assertJsonPath('data.back_image', null)
        ->assertJsonMissingPath('data.id');

    expect($response->json('data.front_image'))->toEndWith('/storage/cards/SOR/1-front.jpg');
});

test('card detail returns 404 for an unknown card', function () {
    $this->getJson('/api/cards/SOR/9999')->assertNotFound();
});

test('card search filters by name', function () {
    makeCard(['name' => 'Luke Skywalker']);
    makeCard(['name' => 'Darth Vader']);

    $this->getJson('/api/cards/search?nome=Luke')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Luke Skywalker');
});

test('card search uses the same filter names as the catalogue page', function () {
    makeCard(['expansion' => 'SOR', 'name' => 'Carta SOR', 'type' => 'Unità']);
    makeCard(['expansion' => 'SHD', 'name' => 'Carta SHD', 'type' => 'Unità']);
    makeCard(['expansion' => 'SHD', 'name' => 'Evento SHD', 'type' => 'Evento']);

    $query = http_build_query(['espansioni' => ['SHD'], 'tipi' => ['Unità']]);

    $this->getJson("/api/cards/search?{$query}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Carta SHD');
});

test('card search filters by aspect id', function () {
    $aspect = Aspect::create(['name' => 'Vigilanza', 'slug' => 'vigilance', 'order' => 1, 'color' => '#4073d4']);
    $withAspect = makeCard(['name' => 'Con aspetto']);
    makeCard(['name' => 'Senza aspetto']);
    $withAspect->aspects()->attach($aspect->id);

    $query = http_build_query(['aspetti' => [$aspect->id]]);

    $this->getJson("/api/cards/search?{$query}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Con aspetto');
});

test('card search applies the cost range', function () {
    makeCard(['name' => 'Economica', 'cost' => 1]);
    makeCard(['name' => 'Media', 'cost' => 3]);
    makeCard(['name' => 'Costosa', 'cost' => 6]);

    // limiti nel database: 1 e 6, quindi 3 e 5 sono entrambi filtri attivi
    $this->getJson('/api/cards/search?costo_min=3&costo_max=5')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Media');
});

test('card search paginates by 24 and keeps the filters in the links', function () {
    foreach (range(1, 30) as $i) {
        makeCard(['name' => "Droide {$i}"]);
    }
    makeCard(['name' => 'Wookiee']);

    $first = $this->getJson('/api/cards/search?nome=Droide')
        ->assertOk()
        ->assertJsonCount(24, 'data')
        ->assertJsonPath('meta.total', 30)
        ->assertJsonPath('meta.current_page', 1);

    expect($first->json('links.next'))->toContain('nome=Droide')->toContain('page=2');

    $this->getJson('/api/cards/search?nome=Droide&page=2')
        ->assertOk()
        ->assertJsonCount(6, 'data')
        ->assertJsonPath('meta.current_page', 2);
});

test('deck detail returns a public deck with the quantities', function () {
    $user = User::factory()->create(['name' => 'ricky']);
    $card = makeCard(['name' => 'Luke Skywalker']);
    makeDeck($user, [[$card, 3]], ['name' => 'mazzo-test']);

    $this->getJson('/api/decks/ricky/mazzo-test')
        ->assertOk()
        ->assertJsonPath('data.name', 'mazzo-test')
        ->assertJsonPath('data.format', 'premier')
        ->assertJsonPath('data.is_public', true)
        ->assertJsonPath('data.version', 1)
        ->assertJsonCount(1, 'data.cards')
        ->assertJsonPath('data.cards.0.cid', $card->cid)
        ->assertJsonPath('data.cards.0.quantity', 3);
});

test('deck detail returns 404 for a private deck', function () {
    $user = User::factory()->create(['name' => 'ricky']);
    makeDeck($user, [], ['name' => 'mazzo-test', 'is_public' => false]);

    $this->getJson('/api/decks/ricky/mazzo-test')->assertNotFound();
});

test('deck detail returns only the latest version', function () {
    $user = User::factory()->create(['name' => 'ricky']);
    $card = makeCard();
    $v1 = makeDeck($user, [[$card, 1]], ['name' => 'mazzo-test']);
    makeDeck($user, [[$card, 2]], ['name' => 'mazzo-test', 'version' => 2, 'previous_version_id' => $v1->id]);

    $this->getJson('/api/decks/ricky/mazzo-test')
        ->assertOk()
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.cards.0.quantity', 2);
});

test('deck detail returns 404 when the latest version is private even if an older one is public', function () {
    $user = User::factory()->create(['name' => 'ricky']);
    $v1 = makeDeck($user, [], ['name' => 'mazzo-test']);
    makeDeck($user, [], ['name' => 'mazzo-test', 'version' => 2, 'previous_version_id' => $v1->id, 'is_public' => false]);

    $this->getJson('/api/decks/ricky/mazzo-test')->assertNotFound();
});

test('decks with the same name of different users do not collide', function () {
    $cardA = makeCard(['name' => 'Carta di ricky']);
    $cardB = makeCard(['name' => 'Carta di luke']);
    makeDeck(User::factory()->create(['name' => 'ricky']), [[$cardA, 1]], ['name' => 'mazzo-test']);
    makeDeck(User::factory()->create(['name' => 'luke']), [[$cardB, 1]], ['name' => 'mazzo-test']);

    $this->getJson('/api/decks/luke/mazzo-test')
        ->assertOk()
        ->assertJsonPath('data.cards.0.cid', $cardB->cid);
});

test('deck detail returns 404 for an unknown user or deck', function () {
    $user = User::factory()->create(['name' => 'ricky']);
    makeDeck($user, [], ['name' => 'mazzo-test']);

    $this->getJson('/api/decks/nessuno/mazzo-test')->assertNotFound();
    $this->getJson('/api/decks/ricky/non-esiste')->assertNotFound();
});

test('the API is limited to 60 requests per minute', function () {
    $this->getJson('/api/cards/search')
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', '60');

    foreach (range(2, 60) as $i) {
        $this->getJson('/api/cards/search')->assertOk();
    }

    $this->getJson('/api/cards/search')->assertStatus(429);
});
