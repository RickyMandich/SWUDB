<?php

use App\Models\CollectionCard;
use App\Models\User;
use App\Services\DeckGapCalculator;

function ownCopies(User $user, $card, int $quantity): void
{
    CollectionCard::create(['user_id' => $user->id, 'card_id' => $card->id, 'quantity' => $quantity]);
}

it('conta le copie delle varianti come copie dell\'originale', function () {
    $user = User::factory()->create();
    $original = makeCard(['expansion' => 'SOR']);
    $hyperspace = makeCard(['expansion' => 'SOROP', 'original_card_id' => $original->id, 'variant_type' => 'Hyperspace']);
    $deck = makeDeck($user, [[$original, 3]]);
    ownCopies($user, $original, 1);
    ownCopies($user, $hyperspace, 1);

    $gap = app(DeckGapCalculator::class)->forDeck($deck->load('cards'), $user);

    expect($gap['present'])->toEqual([$original->id => 2])
        ->and($gap['missing'])->toEqual([$original->id => 1])
        ->and($gap['reservedElsewhere'])->toBe([]);
});

it('chi ha solo una variante non ha l\'originale tra le mancanti', function () {
    $user = User::factory()->create();
    $original = makeCard(['expansion' => 'SOR']);
    $showcase = makeCard(['expansion' => 'SORSH', 'original_card_id' => $original->id, 'variant_type' => 'Showcase']);
    $deck = makeDeck($user, [[$original, 1]]);
    ownCopies($user, $showcase, 1);

    $gap = app(DeckGapCalculator::class)->forDeck($deck->load('cards'), $user);

    expect($gap['missing'])->toBe([])
        ->and($gap['present'])->toEqual([$original->id => 1]);
});

it('le copie impegnate in un altro mazzo assemblato si sottraggono anche se la copia posseduta è una variante', function () {
    $user = User::factory()->create();
    $original = makeCard(['expansion' => 'SOR']);
    $hyperspace = makeCard(['expansion' => 'SOROP', 'original_card_id' => $original->id, 'variant_type' => 'Hyperspace']);
    makeDeck($user, [[$original, 1]], ['name' => 'Altro mazzo', 'assembled' => true]);
    $deck = makeDeck($user, [[$original, 1]], ['name' => 'Questo mazzo']);
    ownCopies($user, $hyperspace, 1);

    $gap = app(DeckGapCalculator::class)->forDeck($deck->load('cards'), $user);

    expect($gap['present'])->toBe([])
        ->and($gap['reservedElsewhere'])->toEqual([$original->id => 1])
        ->and($gap['missing'])->toBe([]);
});

it('le copie di un altro utente non contano', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $original = makeCard(['expansion' => 'SOR']);
    $deck = makeDeck($user, [[$original, 2]]);
    ownCopies($other, $original, 5);

    $gap = app(DeckGapCalculator::class)->forDeck($deck->load('cards'), $user);

    expect($gap['missing'])->toEqual([$original->id => 2]);
});
