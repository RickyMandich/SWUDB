<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Aspect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Crea aspetti predefiniti
        $vigilanza = Aspect::create(['nome' => 'Vigilanza', 'slug' => 'vigilanza', 'colore' => '#4073d4']);
        $autorita = Aspect::create(['nome' => 'Autorità', 'slug' => 'autorita', 'colore' => '#6faf2f']);
        $eroismo = Aspect::create(['nome' => 'Eroismo', 'slug' => 'eroismo', 'colore' => '#ffffff']);

        // Crea carte di test
        $card1 = Card::create([
            'cid' => 'test-1',
            'nome' => 'Luke Skywalker',
            'numero' => 1,
            'espansione' => 'SOR',
            'tipo' => 'Unità',
            'costo' => 5,
            'rarita' => 'Leggendaria',
            'descrizione' => 'Test',
            'tratti' => 'Ribelle',
            'artista' => 'Artista 1'
        ]);
        $card1->aspects()->attach($vigilanza->id, ['sort_order' => 0]);
        $card1->aspects()->attach($eroismo->id, ['sort_order' => 1]);

        $card2 = Card::create([
            'cid' => 'test-2',
            'nome' => 'Darth Vader',
            'numero' => 2,
            'espansione' => 'SOR',
            'tipo' => 'Unità',
            'costo' => 7,
            'rarita' => 'Leggendaria',
            'descrizione' => 'Test',
            'tratti' => 'Impero',
            'artista' => 'Artista 2'
        ]);
        $card2->aspects()->attach($autorita->id, ['sort_order' => 0]);
    }

    /** @test */
    public function it_can_access_cards_index()
    {
        $response = $this->get('/carte');
        $response->assertStatus(200);
    }

    /** @test */
    public function it_can_see_card_details()
    {
        $response = $this->get('/carta/SOR/1');
        $response->assertStatus(200);
        $response->assertSee('Luke Skywalker');
        $response->assertSee('Vigilanza');
        $response->assertSee('Eroismo');
    }

    /** @test */
    public function it_can_search_cards_by_name()
    {
        // Questo test richiederebbe l'esecuzione di Livewire o il controllo dell'API
        $response = $this->get('/carte?nome=Luke');
        $response->assertStatus(200);
        $response->assertSee('Luke Skywalker');
    }
}
