<?php

use App\Enums\DeckFormat;
use App\Models\Deck;
use App\Models\User;
use App\Services\DeckImporter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Importer that skips the DNS lookup (tests have no network)
 * Importer che salta la risoluzione DNS (nei test non c'è rete)
 */
function publicImporter(): DeckImporter
{
    return new class extends DeckImporter
    {
        protected function isPublicHost(string $host): bool
        {
            return true;
        }
    };
}

/**
 * Quantities of a deck as [card id => quantity]
 * Quantità di un mazzo come [id carta => quantità]
 */
function deckQuantities(Deck $deck): array
{
    return $deck->fresh()->cards->mapWithKeys(fn ($card) => [$card->id => (int) $card->pivot->quantity])->all();
}

// ---------------------------------------------------------------- JSON

test('official json resolves cards by expansion and number, zero padding included', function () {
    $leader = makeCard(['type' => 'Leader', 'expansion' => 'JTL', 'number' => 5]);
    $unit = makeCard();
    $user = User::factory()->create();

    $json = json_encode([
        'leader' => ['id' => 'JTL_005', 'count' => 1], // SOR_005 e SOR_5 sono la stessa carta
        'deck' => [['id' => "{$unit->expansion}_{$unit->number}", 'count' => 3]],
    ]);

    $result = (new DeckImporter)->fromContent($json, $user, 'Prova', DeckFormat::Premier);

    expect($result['deck'])->not->toBeNull()
        ->and($result['notFound'])->toBe([])
        ->and(deckQuantities($result['deck']))->toEqual([$leader->id => 1, $unit->id => 3])
        ->and($result['deck']->version)->toBe(1)
        ->and($result['deck']->user_id)->toBe($user->id);
});

test('an unknown id goes to notFound and the deck is created anyway', function () {
    $unit = makeCard();

    $json = json_encode(['deck' => [
        ['id' => "{$unit->expansion}_{$unit->number}", 'count' => 2],
        ['id' => 'SOR_9999', 'count' => 1],
        ['id' => 'non-valido', 'count' => 1],
    ]]);

    $result = (new DeckImporter)->fromContent($json, User::factory()->create(), 'Prova', DeckFormat::Eternal);

    expect($result['deck'])->not->toBeNull()
        ->and($result['notFound'])->toBe(['SOR_9999', 'non-valido'])
        ->and(deckQuantities($result['deck']))->toEqual([$unit->id => 2]);
});

test('duplicate rows of the same card add up', function () {
    $unit = makeCard();
    $id = "{$unit->expansion}_{$unit->number}";

    $json = json_encode(['deck' => [['id' => $id, 'count' => 2], ['id' => $id, 'count' => 1]]]);

    $result = (new DeckImporter)->fromContent($json, User::factory()->create(), 'Prova', DeckFormat::Premier);

    expect(deckQuantities($result['deck']))->toEqual([$unit->id => 3]);
});

test('the sideboard of the official json is counted and not imported', function () {
    $unit = makeCard();
    $other = makeCard();

    $json = json_encode([
        'deck' => [['id' => "{$unit->expansion}_{$unit->number}", 'count' => 1]],
        'sideboard' => [['id' => "{$other->expansion}_{$other->number}", 'count' => 2]],
    ]);

    $result = (new DeckImporter)->fromContent($json, User::factory()->create(), 'Prova', DeckFormat::Premier);

    expect($result['sideboardIgnored'])->toBe(2)
        ->and(deckQuantities($result['deck']))->toEqual([$unit->id => 1]);
});

test('native swudb json is converted into deck and sideboard', function () {
    $leader = makeCard(['type' => 'Leader']);
    $base = makeCard(['type' => 'Base']);
    $unit = makeCard();

    $ref = fn ($card) => ['defaultExpansionAbbreviation' => $card->expansion, 'defaultCardNumber' => $card->number];

    $json = json_encode([
        'leader' => $ref($leader),
        'base' => $ref($base),
        'shuffledDeck' => [['card' => $ref($unit), 'count' => 2, 'sideboardCount' => 1]],
    ]);

    $result = (new DeckImporter)->fromContent($json, User::factory()->create(), 'Prova', DeckFormat::Premier);

    expect(deckQuantities($result['deck']))->toEqual([$leader->id => 1, $base->id => 1, $unit->id => 2])
        ->and($result['sideboardIgnored'])->toBe(1);
});

// ---------------------------------------------------------------- TXT

test('text import resolves cards by name and title and ignores the sideboard', function () {
    $leader = makeCard(['type' => 'Leader', 'name' => 'Luke Skywalker', 'title' => 'Jedi Knight']);
    $base = makeCard(['type' => 'Base', 'name' => 'Echo Base']);
    $unit = makeCard(['name' => 'Alpha']);
    makeCard(['name' => 'Beta']);

    $text = "Leaders\n1 | Luke Skywalker | Jedi Knight\n\nBase\n1 | Echo Base\n\nDeck\n3 | Alpha\n\nSideboard\n2 | Beta\n";

    $result = (new DeckImporter)->fromContent($text, User::factory()->create(), 'Prova', DeckFormat::Premier);

    expect($result['notFound'])->toBe([])
        ->and($result['sideboardIgnored'])->toBe(2)
        ->and(deckQuantities($result['deck']))->toEqual([$leader->id => 1, $base->id => 1, $unit->id => 3]);
});

test('text lines outside a section or malformed are reported, not lost', function () {
    $unit = makeCard(['name' => 'Alpha']);

    $text = "riga fuori sezione\n\nDeck\n2 | Alpha\nnon valida\n1 | Carta inesistente\n";

    $result = (new DeckImporter)->fromContent($text, User::factory()->create(), 'Prova', DeckFormat::Premier);

    expect($result['notFound'])->toBe(['riga fuori sezione', 'non valida', '1 | Carta inesistente'])
        ->and(deckQuantities($result['deck']))->toEqual([$unit->id => 2]);
});

test('text import understands the old SWUDB form with expansion and number', function () {
    $unit = makeCard(['expansion' => 'LAW', 'number' => 134, 'name' => 'Qualcosa']);

    $text = "Deck\n3 | LAW-134 | Qualcosa\n";

    $result = (new DeckImporter)->fromContent($text, User::factory()->create(), 'Prova', DeckFormat::Premier);

    expect($result['notFound'])->toBe([])
        ->and(deckQuantities($result['deck']))->toEqual([$unit->id => 3]);
});

// ---------------------------------------------------------------- nessuna carta

test('when no card is recognised no deck is created', function () {
    $result = (new DeckImporter)->fromContent("Deck\n1 | Non esiste\n", User::factory()->create(), 'Prova', DeckFormat::Premier);

    expect($result['deck'])->toBeNull()
        ->and($result['error'])->not->toBeNull()
        ->and($result['notFound'])->toBe(['1 | Non esiste'])
        ->and(Deck::count())->toBe(0);
});

// ---------------------------------------------------------------- URL

test('an unreachable url fails without creating a deck', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    $result = publicImporter()->fromUrl('https://example.com/mazzo.json', User::factory()->create(), 'Prova', DeckFormat::Premier);

    expect($result['deck'])->toBeNull()->and($result['error'])->toContain('non raggiungibile')
        ->and(Deck::count())->toBe(0);
});

test('a redirect is not followed', function () {
    Http::fake(['*' => Http::response('', 302, ['Location' => 'https://example.org/altro'])]);

    $result = publicImporter()->fromUrl('https://example.com/mazzo.json', User::factory()->create(), 'Prova', DeckFormat::Premier);

    expect($result['deck'])->toBeNull()->and($result['error'])->toContain('rimanda');
});

test('an html response is rejected before decoding', function () {
    Http::fake(['*' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html'])]);

    $result = publicImporter()->fromUrl('https://example.com/mazzo.json', User::factory()->create(), 'Prova', DeckFormat::Premier);

    expect($result['deck'])->toBeNull()->and($result['error'])->toContain('text/html');
});

test('a plain http url is refused without any request', function () {
    Http::fake();

    $result = publicImporter()->fromUrl('http://example.com/mazzo.json', User::factory()->create(), 'Prova', DeckFormat::Premier);

    expect($result['deck'])->toBeNull()->and($result['error'])->toContain('https');
    Http::assertNothingSent();
});

test('an http error status is reported', function () {
    Http::fake(['*' => Http::response('', 500)]);

    $result = publicImporter()->fromUrl('https://example.com/mazzo.json', User::factory()->create(), 'Prova', DeckFormat::Premier);

    expect($result['deck'])->toBeNull()->and($result['error'])->toContain('500');
});

test('a response over 1 MB is refused', function () {
    Http::fake(['*' => Http::response(str_repeat('a', 1_048_577), 200, ['Content-Type' => 'text/plain'])]);

    $result = publicImporter()->fromUrl('https://example.com/mazzo.txt', User::factory()->create(), 'Prova', DeckFormat::Premier);

    expect($result['deck'])->toBeNull()->and($result['error'])->toContain('troppo grande');
});

test('a host that does not resolve is refused by the public host check', function () {
    Http::fake();

    // Importer vero, senza la sottoclasse: gethostbyname() non risolve il dominio .invalid
    $result = (new DeckImporter)->fromUrl('https://host-che-non-esiste.invalid/mazzo.json', User::factory()->create(), 'Prova', DeckFormat::Premier);

    expect($result['deck'])->toBeNull()->and($result['error'])->toContain('non consentito');
    Http::assertNothingSent();
});

test('a swudb.com deck link is rewritten to its api and imported', function () {
    $unit = makeCard();

    Http::fake(['swudb.com/*' => Http::response(
        json_encode(['shuffledDeck' => [[
            'card' => ['defaultExpansionAbbreviation' => $unit->expansion, 'defaultCardNumber' => $unit->number],
            'count' => 3,
        ]]]),
        200,
        ['Content-Type' => 'application/json'],
    )]);

    $result = publicImporter()->fromUrl('https://swudb.com/deck/ABC123', User::factory()->create(), 'Prova', DeckFormat::Premier);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://swudb.com/api/deck/ABC123');
    expect(deckQuantities($result['deck']))->toEqual([$unit->id => 3]);
});
