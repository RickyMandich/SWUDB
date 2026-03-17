<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Card;
use App\Models\Aspect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeckManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup initial data
        Aspect::create(['nome' => 'Eroismo', 'slug' => 'eroismo', 'colore' => '#ffffff']);
        Card::create([
            'cid' => 'test-card',
            'nome' => 'Test Card',
            'numero' => 1,
            'espansione' => 'SOR',
            'tipo' => 'Unità',
            'costo' => 1,
            'rarita' => 'Comune',
            'descrizione' => 'Test',
            'tratti' => 'Test',
            'artista' => 'Test'
        ]);
    }

    /** @test */
    public function authenticated_users_can_create_a_deck()
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)->post('/mazzo/create', [
            'nome' => 'Il mio mazzo',
            'pubblico' => 1
        ]);
        
        $response->assertStatus(200); // Oppure redirect se previsto dal controller
        $this->assertDatabaseHas('Decks', [
            'nome' => 'Il mio mazzo',
            'user' => $user->id
        ]);
    }

    /** @test */
    public function guest_users_cannot_access_collection()
    {
        $response = $this->get('/collezione');
        $response->assertRedirect('/login');
    }
}
