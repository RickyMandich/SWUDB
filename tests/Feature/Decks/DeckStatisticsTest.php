<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deck used by the statistics tests:
 * - unit A, cost 2, power 4, health 5, x3 (traits Ribelle)
 * - unit B, cost 4, power 2, health 1, x1 (traits Ribelle, Pilota)
 * - event C, cost 3, x2
 * plus a leader and a base, which must not count
 * Mazzo usato dai test delle statistiche (leader e base non devono contare)
 */
function statisticsDeck(): array
{
    DB::table('traits')->insert([['name' => 'Ribelle'], ['name' => 'Pilota']]);

    $a = makeCard(['name' => 'A', 'cost' => 2, 'power' => 4, 'health' => 5]);
    $b = makeCard(['name' => 'B', 'cost' => 4, 'power' => 2, 'health' => 1]);
    $c = makeCard(['name' => 'C', 'type' => 'Evento', 'cost' => 3]);
    $leader = makeCard(['type' => 'Leader', 'cost' => 6, 'power' => 6, 'health' => 6]);
    $base = makeCard(['type' => 'Base', 'cost' => 0]);

    $a->traits()->attach(['Ribelle']);
    $b->traits()->attach(['Ribelle', 'Pilota']);

    $user = User::factory()->create(['name' => 'alice']);
    $deck = makeDeck($user, [[$a, 3], [$b, 1], [$c, 2], [$leader, 1], [$base, 1]], ['name' => 'stat']);

    return [$user, $deck];
}

test('averages are weighted by copies and leader and base are left out', function () {
    [$user, $deck] = statisticsDeck();

    $response = $this->get(route('decks.show', [$user->name, $deck->name]))->assertOk();

    expect($response->viewData('total'))->toBe(6)
        ->and($response->viewData('avgPower'))->toEqual(3.5)   // (3*4 + 1*2) / 4
        ->and($response->viewData('avgHealth'))->toEqual(4)    // (3*5 + 1*1) / 4
        ->and($response->viewData('byType')->all())->toEqual(['Unità' => 4, 'Evento' => 2]);
});

test('the cost curve has no gaps from zero to the maximum cost', function () {
    [$user, $deck] = statisticsDeck();

    $curve = $this->get(route('decks.show', [$user->name, $deck->name]))->viewData('costCurve');

    expect($curve->all())->toEqual([0 => 0, 1 => 0, 2 => 3, 3 => 2, 4 => 1]);
});

test('traits are weighted by copies and sorted by count', function () {
    [$user, $deck] = statisticsDeck();

    $traits = $this->get(route('decks.show', [$user->name, $deck->name]))->viewData('traits');

    expect($traits)->toEqual(['Ribelle' => 4, 'Pilota' => 1])
        ->and(array_keys($traits))->toBe(['Ribelle', 'Pilota']);
});

test('a deck without cards besides leader and base still answers 200 with no averages', function () {
    $user = User::factory()->create(['name' => 'alice']);
    $deck = makeDeck($user, [[makeCard(['type' => 'Leader']), 1]], ['name' => 'vuoto']);

    $response = $this->get(route('decks.show', [$user->name, $deck->name]))->assertOk();

    expect($response->viewData('total'))->toBe(0)
        ->and($response->viewData('avgPower'))->toBeNull()
        ->and($response->viewData('avgHealth'))->toBeNull();
});

test('statistics of a private deck are forbidden to guests', function () {
    $user = User::factory()->create(['name' => 'alice']);
    $deck = makeDeck($user, [], ['name' => 'segreto', 'is_public' => false]);

    $this->get(route('decks.show', [$user->name, $deck->name]))->assertForbidden();
});

test('statistics follow the version in the url', function () {
    $user = User::factory()->create(['name' => 'alice']);
    $v1 = makeDeck($user, [[makeCard(['power' => 2, 'health' => 2]), 1]], ['name' => 'versioni']);
    makeDeck($user, [[makeCard(['power' => 8, 'health' => 8]), 1]], ['name' => 'versioni', 'version' => 2, 'previous_version_id' => $v1->id]);

    $first = $this->get(route('decks.show', [$user->name, 'versioni', 1]))->viewData('avgPower');
    $latest = $this->get(route('decks.show', [$user->name, 'versioni']))->viewData('avgPower');

    expect($first)->toEqual(2)->and($latest)->toEqual(8);
});

test('legacy statistics route redirects to deck show with statistics fragment', function () {
    [$user, $deck] = statisticsDeck();

    $this->get(route('decks.statistics', [$user->name, $deck->name]))
        ->assertRedirect(route('decks.show', [$user->name, $deck->name]) . '#statistiche');
});

test('statistics calculate cost curve by type and power health by cost', function () {
    [$user, $deck] = statisticsDeck();

    $response = $this->get(route('decks.show', [$user->name, $deck->name]))->assertOk();

    $byType = $response->viewData('costCurveByType');
    expect($byType)->toHaveKeys(['Unità', 'Miglioria', 'Evento'])
        ->and($byType['Unità'])->toEqual([0, 0, 3, 0, 1])
        ->and($byType['Evento'])->toEqual([0, 0, 0, 2, 0]);

    $ph = $response->viewData('powerHealthByCost');
    expect($ph)->toEqual([
        ['cost' => 2, 'power' => 4.0, 'health' => 5.0],
        ['cost' => 4, 'power' => 2.0, 'health' => 1.0],
    ]);

    $rarities = $response->viewData('rarityStats');
    expect($rarities)->toBeArray();
});
