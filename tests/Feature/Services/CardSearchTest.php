<?php

use App\Models\Aspect;
use App\Models\Card;
use App\Models\Expansion;
use App\Models\CardTrait;
use App\Services\CardSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->search = new CardSearch();
    
    // Default bounds in the test
    // We will create some basic cards to set the limits

    Expansion::create([
        'expansion' => 'SOR',
        'legal_date' => '2024-03-08',
        'rotation' => '1',
    ]);
    Expansion::create([
        'expansion' => 'SHD',
        'legal_date' => '2024-07-12',
        'rotation' => '1',
    ]);

    Card::create(['expansion' => 'SOR', 'number' => 1, 'name' => 'Card 1', 'type' => 'Leader', 'cost' => 1, 'health' => 10, 'power' => 2, 'unique_card' => true, 'cid' => 'c1', 'rarity' => 'Comune']);
    Card::create(['expansion' => 'SOR', 'number' => 2, 'name' => 'Card 2', 'type' => 'Unità', 'cost' => 5, 'health' => 20, 'power' => 5, 'unique_card' => false, 'cid' => 'c2', 'rarity' => 'Non Comune']);
    Card::create(['expansion' => 'SHD', 'number' => 1, 'name' => 'Card 3', 'type' => 'Evento', 'cost' => null, 'health' => null, 'power' => null, 'unique_card' => false, 'cid' => 'c3', 'rarity' => 'Rara']);
    Card::create(['expansion' => 'SOR', 'number' => 3, 'name' => 'Card 4', 'type' => 'Unità', 'cost' => 10, 'health' => 30, 'power' => 10, 'unique_card' => true, 'cid' => 'c4', 'rarity' => 'Leggendaria']);
});

test('it filters by name, expansions and types', function () {
    $query = Card::query();
    $filters = ['nome' => 'Card', 'espansioni' => ['SOR'], 'tipi' => ['Unità']];
    
    $results = $this->search->apply($query, $filters)->get();
    
    expect($results)->toHaveCount(2)
        ->and($results->pluck('name')->toArray())->toContain('Card 2', 'Card 4');
});

test('it filters by aspects in AND logic', function () {
    $aspect1 = Aspect::create(['name' => 'Heroism', 'slug' => 'heroism', 'order' => 1, 'color' => '#fff']);
    $aspect2 = Aspect::create(['name' => 'Command', 'slug' => 'command', 'order' => 2, 'color' => '#000']);
    
    $card1 = Card::where('name', 'Card 1')->first();
    $card1->aspects()->attach([$aspect1->id, $aspect2->id]);
    
    $card2 = Card::where('name', 'Card 2')->first();
    $card2->aspects()->attach([$aspect1->id]);
    
    $query = Card::query();
    $filters = ['aspetti' => [$aspect1->id, $aspect2->id]];
    
    $results = $this->search->apply($query, $filters)->get();
    
    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Card 1');
});

test('it filters by traits in AND logic', function () {
    $trait1 = CardTrait::create(['name' => 'Rebel']);
    $trait2 = CardTrait::create(['name' => 'Force']);
    
    $card1 = Card::where('name', 'Card 1')->first();
    $card1->traits()->attach([$trait1->name, $trait2->name]);
    
    $card2 = Card::where('name', 'Card 2')->first();
    $card2->traits()->attach([$trait1->name]);
    
    $query = Card::query();
    $filters = ['tratti' => ['Rebel', 'Force']];
    
    $results = $this->search->apply($query, $filters)->get();
    
    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Card 1');
});

test('it filters by cost, health, power limits', function () {
    $query = Card::query();
    // Restricting range
    $filters = ['costo_min' => 2, 'costo_max' => 8, 'vita_min' => 15, 'vita_max' => 25, 'potenza_min' => 3, 'potenza_max' => 8];
    
    $results = $this->search->apply($query, $filters)->get();
    
    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Card 2');
});

test('it does not filter out NULL values if extreme limits are selected (coincide with bounds)', function () {
    $query = Card::query();
    
    // Bounds for cost are [1, 10]. If we select 1 and 10, it should not filter cost.
    $filters = ['costo_min' => 1, 'costo_max' => 10];
    
    $results = $this->search->apply($query, $filters)->get();
    
    // All 4 cards should be returned (including Card 3 which has NULL cost)
    expect($results)->toHaveCount(4);
});

test('it filters out NULL values if at least one limit is active', function () {
    $query = Card::query();
    
    // Bounds are [1, 10]. We select cost_min = 2, making it active.
    $filters = ['costo_min' => 2, 'costo_max' => 10];
    
    $results = $this->search->apply($query, $filters)->get();
    
    // Card 3 has NULL cost, so it should be excluded. Card 1 has cost 1, excluded.
    expect($results)->toHaveCount(2)
        ->and($results->pluck('name')->toArray())->toContain('Card 2', 'Card 4');
});

test('it filters by unique_card correctly', function () {
    // Tutte (vuoto)
    $results = $this->search->apply(Card::query(), ['unique_card' => ''])->get();
    expect($results)->toHaveCount(4);
    
    // Solo uniche (1)
    $results = $this->search->apply(Card::query(), ['unique_card' => '1'])->get();
    expect($results)->toHaveCount(2)
        ->and($results->pluck('name')->toArray())->toContain('Card 1', 'Card 4');
        
    // Solo non uniche (0)
    $results = $this->search->apply(Card::query(), ['unique_card' => '0'])->get();
    expect($results)->toHaveCount(2)
        ->and($results->pluck('name')->toArray())->toContain('Card 2', 'Card 3');
});
