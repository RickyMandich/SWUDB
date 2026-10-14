<?php

use App\Models\Deck;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Deck owned by a user with a fixed name, so URLs are predictable
 * Mazzo di un utente con nome fisso, così gli URL sono prevedibili
 */
function ownedDeck(string $username = 'alice', array $cards = [], array $attributes = []): array
{
    $user = User::factory()->create(['name' => $username]);

    return [$user, makeDeck($user, $cards, $attributes + ['name' => 'sabine-aggro'])];
}

// ---------------------------------------------------------------- lista

test('the list shows only the latest version of each deck', function () {
    [$user, $v1] = ownedDeck();
    $v2 = makeDeck($user, [], ['name' => 'sabine-aggro', 'version' => 2, 'previous_version_id' => $v1->id]);
    makeDeck($user, [], ['name' => 'sabine-aggro', 'version' => 3, 'previous_version_id' => $v2->id]);

    $decks = $this->get(route('decks.index'))->assertOk()->viewData('decks');

    expect($decks->total())->toBe(1)
        ->and($decks->first()->version)->toBe(3);
});

test('the list hides the private decks of other users from guests', function () {
    ownedDeck('alice', [], ['is_public' => false]);
    ownedDeck('bob', [], ['name' => 'pubblico', 'is_public' => true]);

    $decks = $this->get(route('decks.index'))->assertOk()->viewData('decks');

    expect($decks->pluck('name')->all())->toBe(['pubblico']);
});

test('the list includes the private decks of the logged user', function () {
    [$user] = ownedDeck('alice', [], ['is_public' => false]);

    $decks = $this->actingAs($user)->get(route('decks.index'))->assertOk()->viewData('decks');

    expect($decks->total())->toBe(1);
});

test('a deck without leader and base does not break the list or the deck page', function () {
    [$user, $deck] = ownedDeck();

    $this->get(route('decks.index'))->assertOk();
    $this->get(route('decks.show', [$user->name, $deck->name]))->assertOk(); // regressione di decks.statistics e di baseCard
});

// ---------------------------------------------------------------- show

test('a public deck is visible to guests and a private one is not', function () {
    [$user, $public] = ownedDeck('alice', [[makeCard(), 2]]);
    $private = makeDeck($user, [], ['name' => 'segreto', 'is_public' => false]);

    $this->get(route('decks.show', [$user->name, $public->name]))->assertOk();
    $this->get(route('decks.show', [$user->name, $private->name]))->assertForbidden();
});

test('a private deck is visible to its owner and forbidden to other users', function () {
    [$user, $deck] = ownedDeck('alice', [], ['is_public' => false]);
    $other = User::factory()->create();

    $this->actingAs($user)->get(route('decks.show', [$user->name, $deck->name]))->assertOk();
    $this->actingAs($other)->get(route('decks.show', [$user->name, $deck->name]))->assertForbidden();
});

test('an older version is read only and points to the latest', function () {
    [$user, $v1] = ownedDeck();
    makeDeck($user, [], ['name' => 'sabine-aggro', 'version' => 2, 'previous_version_id' => $v1->id]);

    $this->get(route('decks.show', [$user->name, $v1->name, 1]))
        ->assertOk()
        ->assertSee('in sola lettura');

    $this->get(route('decks.show', [$user->name, $v1->name]))
        ->assertOk()
        ->assertDontSee('in sola lettura');
});

// ---------------------------------------------------------------- edit

test('the edit page is for the owner only', function () {
    [$user, $deck] = ownedDeck();
    $other = User::factory()->create();

    $this->get(route('decks.edit', [$user->name, $deck->name]))->assertRedirect(route('login'));
    $this->actingAs($other)->get(route('decks.edit', [$user->name, $deck->name]))->assertForbidden();
    $this->actingAs($user)->get(route('decks.edit', [$user->name, $deck->name]))->assertOk();
});

test('syncing cards replaces the deck cards and detaches the ones not sent', function () {
    $a = makeCard();
    $b = makeCard();
    [$user, $deck] = ownedDeck('alice', [[$a, 2], [$b, 1]]);

    $this->actingAs($user)
        ->put(route('decks.sync-cards', [$user->name, $deck->name]), [
            'cards' => [['card_id' => $a->id, 'quantity' => 3]],
        ])
        ->assertRedirect(route('decks.edit', [$user->name, $deck->name]));

    expect($deck->fresh()->cards->mapWithKeys(fn ($c) => [$c->id => (int) $c->pivot->quantity])->all())
        ->toEqual([$a->id => 3]);
});

test('syncing cards is forbidden to other users', function () {
    [$user, $deck] = ownedDeck();

    $this->actingAs(User::factory()->create())
        ->put(route('decks.sync-cards', [$user->name, $deck->name]), ['cards' => []])
        ->assertForbidden();
});

test('adding a card adds the quantity to the one already in the deck', function () {
    $a = makeCard();
    [$user, $deck] = ownedDeck('alice', [[$a, 2]]);

    $this->actingAs($user)
        ->post(route('decks.add-card', [$user->name, $deck->name]), ['card_id' => $a->id, 'quantity' => 1])
        ->assertRedirect();

    expect((int) $deck->fresh()->cards->first()->pivot->quantity)->toBe(3);
});

test('removing a card detaches it', function () {
    $a = makeCard();
    [$user, $deck] = ownedDeck('alice', [[$a, 2]]);

    $this->actingAs($user)
        ->delete(route('decks.remove-card', [$user->name, $deck->name, $a->id]))
        ->assertRedirect();

    expect($deck->fresh()->cards)->toHaveCount(0);
});

test('creating a version copies the cards and links the chain, more than once', function () {
    $a = makeCard();
    [$user, $v1] = ownedDeck('alice', [[$a, 2]]);

    $this->actingAs($user)->post(route('decks.create-version', [$user->name, $v1->name]))->assertRedirect();
    $this->actingAs($user)->post(route('decks.create-version', [$user->name, $v1->name]))->assertRedirect();

    $versions = Deck::where('user_id', $user->id)->orderBy('version')->get();
    $v2 = $versions[1];

    expect($versions->pluck('version')->all())->toBe([1, 2, 3])
        ->and($v2->previous_version_id)->toBe($v1->id)
        ->and((int) $v2->cards->first()->pivot->quantity)->toBe(2);

    $chain = $this->get(route('decks.versions', [$user->name, $v1->name]))->assertOk()->viewData('decks');
    expect($chain->pluck('version')->all())->toBe([1, 2, 3]);
});

// ---------------------------------------------------------------- ricerca carte

test('card search is forbidden to other users and guests', function () {
    [$user, $deck] = ownedDeck();

    $this->getJson(route('decks.search-cards', [$user->name, $deck->name]))->assertUnauthorized();
    $this->actingAs(User::factory()->create())
        ->getJson(route('decks.search-cards', [$user->name, $deck->name]))
        ->assertForbidden();
});

test('card search rejects types that cannot be in a deck', function () {
    [$user, $deck] = ownedDeck();

    $this->actingAs($user)
        ->getJson(route('decks.search-cards', [$user->name, $deck->name]).'?tipi[]=Inesistente')
        ->assertUnprocessable();
});

test('card search paginates by 30 and carries the totals', function () {
    foreach (range(1, 31) as $i) {
        makeCard();
    }
    [$user, $deck] = ownedDeck();
    $url = route('decks.search-cards', [$user->name, $deck->name]);

    $first = $this->actingAs($user)->getJson($url)->assertOk()->json();
    $second = $this->actingAs($user)->getJson($url.'?page=2')->assertOk()->json();

    expect($first['data'])->toHaveCount(30)
        ->and($first['total'])->toBe(31)
        ->and($first['last_page'])->toBe(2)
        ->and($second['data'])->toHaveCount(1)
        ->and($second['current_page'])->toBe(2);
});

test('card search applies the catalogue filters', function () {
    makeCard(['name' => 'Zzz Speciale']);
    makeCard();
    makeCard();
    [$user, $deck] = ownedDeck();

    $response = $this->actingAs($user)
        ->getJson(route('decks.search-cards', [$user->name, $deck->name]).'?nome=Zzz')
        ->assertOk()
        ->json();

    expect($response['total'])->toBe(1)
        ->and($response['data'][0]['name'])->toBe('Zzz Speciale');
});

// ---------------------------------------------------------------- export

test('the json export is an attachment with the expected content', function () {
    $unit = makeCard();
    [$user, $deck] = ownedDeck('alice', [[$unit, 3]]);

    $response = $this->get(route('decks.export', [$user->name, $deck->name, 'json', 1]))->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('attachment')->toContain('sabine-aggro-v1.json')
        ->and(json_decode($response->getContent(), true)['deck'])
        ->toBe([['id' => "{$unit->expansion}_{$unit->number}", 'count' => 3]]);
});

test('the text export is an attachment', function () {
    [$user, $deck] = ownedDeck('alice', [[makeCard(['name' => 'Alpha']), 3]]);

    $response = $this->get(route('decks.export', [$user->name, $deck->name, 'txt', 1]))->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('attachment')
        ->and($response->getContent())->toBe("Deck\n3 | Alpha\n");
});

test('the export of a private deck is forbidden to guests', function () {
    [$user, $deck] = ownedDeck('alice', [], ['is_public' => false]);

    $this->get(route('decks.export', [$user->name, $deck->name, 'json', 1]))->assertForbidden();
});

test('the export accepts only txt and json', function () {
    [$user, $deck] = ownedDeck();

    // URL scritto a mano: route() non genera un URL fuori dai vincoli della rotta
    $this->get("/mazzo/{$user->name}/{$deck->name}/esporta/xml/1")->assertNotFound();
});

// ---------------------------------------------------------------- import

test('a logged user imports a json file as a new deck and gets the warnings', function () {
    $unit = makeCard();
    $user = User::factory()->create(['name' => 'alice']);
    $json = json_encode(['deck' => [
        ['id' => "{$unit->expansion}_{$unit->number}", 'count' => 2],
        ['id' => 'SOR_9999', 'count' => 1],
    ]]);

    $this->actingAs($user)
        ->post(route('decks.import.store'), [
            'name' => 'Importato',
            'format' => 'premier',
            'is_public' => '0',
            'file' => UploadedFile::fake()->createWithContent('mazzo.json', $json),
        ])
        ->assertRedirect(route('decks.edit', ['alice', 'Importato']))
        ->assertSessionHas('import-warnings', ['SOR_9999']);

    $deck = Deck::where('name', 'Importato')->first();
    expect($deck->user_id)->toBe($user->id)
        ->and($deck->version)->toBe(1)
        ->and((int) $deck->cards->first()->pivot->quantity)->toBe(2);
});

test('importing with a name already in use is a validation error', function () {
    $user = User::factory()->create();
    makeDeck($user, [], ['name' => 'Occupato']);

    $this->actingAs($user)
        ->post(route('decks.import.store'), [
            'name' => 'Occupato',
            'format' => 'premier',
            'file' => UploadedFile::fake()->createWithContent('mazzo.json', '{}'),
        ])
        ->assertSessionHasErrors('name');
});

test('an import with no recognised card shows the error and creates nothing', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('decks.import.store'), [
            'name' => 'Vuoto',
            'format' => 'premier',
            'file' => UploadedFile::fake()->createWithContent('mazzo.txt', "Deck\n1 | Non esiste\n"),
        ])
        ->assertSessionHasErrors('import');

    expect(Deck::count())->toBe(0);
});

test('guests are redirected to the login on both import routes', function () {
    $this->get(route('decks.import'))->assertRedirect(route('login'));
    $this->post(route('decks.import.store'), ['name' => 'X'])->assertRedirect(route('login'));
});
