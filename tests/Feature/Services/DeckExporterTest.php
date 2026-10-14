<?php

use App\Enums\DeckFormat;
use App\Models\User;
use App\Services\DeckExporter;
use App\Services\DeckImporter;

test('toText writes the Leaders, Base and Deck sections in order, sorted by cost then name', function () {
    $user = User::factory()->create(['name' => 'alice']);
    $leader = makeCard(['type' => 'Leader', 'name' => 'Luke Skywalker', 'title' => 'Jedi Knight', 'cost' => 6]);
    $base = makeCard(['type' => 'Base', 'name' => 'Echo Base', 'cost' => 0]);
    $beta = makeCard(['name' => 'Beta', 'cost' => 2]);
    $alpha = makeCard(['name' => 'Alpha', 'cost' => 2]);
    $cheap = makeCard(['name' => 'Zeta', 'cost' => 1]);

    $deck = makeDeck($user, [[$leader, 1], [$base, 1], [$beta, 2], [$alpha, 3], [$cheap, 1]]);

    expect((new DeckExporter)->toText($deck))->toBe(
        "Leaders\n1 | Luke Skywalker | Jedi Knight\n\n"
        ."Base\n1 | Echo Base\n\n"
        ."Deck\n1 | Zeta\n3 | Alpha\n2 | Beta\n"
    );
});

test('toText leaves out empty sections', function () {
    $user = User::factory()->create();
    $deck = makeDeck($user, [[makeCard(['name' => 'Alpha']), 3]]);

    expect((new DeckExporter)->toText($deck))->toBe("Deck\n3 | Alpha\n");
});

test('toJson uses expansion_number ids without leading zeros and the author name', function () {
    $user = User::factory()->create(['name' => 'alice']);
    $leader = makeCard(['type' => 'Leader', 'expansion' => 'JTL', 'number' => 5]);
    $base = makeCard(['type' => 'Base']);
    $unit = makeCard();

    $deck = makeDeck($user, [[$leader, 1], [$base, 1], [$unit, 3]], ['name' => 'Il mio mazzo']);

    $data = json_decode((new DeckExporter)->toJson($deck), true);

    expect($data['metadata'])->toBe(['name' => 'Il mio mazzo', 'author' => 'alice'])
        ->and($data['leader'])->toBe(['id' => 'JTL_5', 'count' => 1])
        ->and($data['base'])->toBe(['id' => "{$base->expansion}_{$base->number}", 'count' => 1])
        ->and($data['deck'])->toBe([['id' => "{$unit->expansion}_{$unit->number}", 'count' => 3]])
        ->and($data)->not->toHaveKey('secondleader');
});

test('toJson adds secondleader for a deck with two leaders', function () {
    $user = User::factory()->create();
    $first = makeCard(['type' => 'Leader', 'name' => 'A Leader', 'cost' => 5]);
    $second = makeCard(['type' => 'Leader', 'name' => 'B Leader', 'cost' => 5]);

    $deck = makeDeck($user, [[$first, 1], [$second, 1]], ['format' => 'twin_suns']);

    $data = json_decode((new DeckExporter)->toJson($deck), true);

    expect($data['leader']['id'])->toBe("{$first->expansion}_{$first->number}")
        ->and($data['secondleader']['id'])->toBe("{$second->expansion}_{$second->number}");
});

test('a json export imports back to the same cards and quantities', function () {
    $user = User::factory()->create();
    $leader = makeCard(['type' => 'Leader', 'name' => 'Luke Skywalker', 'title' => 'Jedi Knight']);
    $base = makeCard(['type' => 'Base', 'name' => 'Echo Base']);
    $alpha = makeCard(['name' => 'Alpha']);
    $beta = makeCard(['name' => 'Beta']);
    $deck = makeDeck($user, [[$leader, 1], [$base, 1], [$alpha, 3], [$beta, 2]]);

    $result = (new DeckImporter)->fromContent((new DeckExporter)->toJson($deck), $user, 'Copia', DeckFormat::Premier);

    expect($result['notFound'])->toBe([])
        ->and($result['deck']->fresh()->cards->mapWithKeys(fn ($c) => [$c->id => (int) $c->pivot->quantity])->all())
        ->toEqual([$leader->id => 1, $base->id => 1, $alpha->id => 3, $beta->id => 2]);
});

test('a text export imports back to the same cards and quantities', function () {
    $user = User::factory()->create();
    $leader = makeCard(['type' => 'Leader', 'name' => 'Luke Skywalker', 'title' => 'Jedi Knight']);
    $base = makeCard(['type' => 'Base', 'name' => 'Echo Base']);
    $alpha = makeCard(['name' => 'Alpha']);
    $beta = makeCard(['name' => 'Beta']);
    $deck = makeDeck($user, [[$leader, 1], [$base, 1], [$alpha, 3], [$beta, 2]]);

    $result = (new DeckImporter)->fromContent((new DeckExporter)->toText($deck), $user, 'Copia', DeckFormat::Premier);

    expect($result['notFound'])->toBe([])
        ->and($result['deck']->fresh()->cards->mapWithKeys(fn ($c) => [$c->id => (int) $c->pivot->quantity])->all())
        ->toEqual([$leader->id => 1, $base->id => 1, $alpha->id => 3, $beta->id => 2]);
});
