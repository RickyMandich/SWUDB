<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminToolsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function unauthorized_users_cannot_access_admin_logs()
    {
        $response = $this->get('/admin/logs');
        $response->assertRedirect('/login');
    }

    /** @test */
    public function non_admin_users_cannot_access_admin_logs()
    {
        $user = User::factory()->create(['admin' => 0]);
        
        $response = $this->actingAs($user)->get('/admin/logs');
        $response->assertStatus(403);
    }

    /** @test */
    public function admin_users_can_access_admin_logs()
    {
        $user = User::factory()->create(['admin' => 1]);
        
        $response = $this->actingAs($user)->get('/admin/logs');
        $response->assertStatus(200);
    }
}
