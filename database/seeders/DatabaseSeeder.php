<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
        ]);

        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $user = User::create(
            [
                'name' => 'RickyMandich',
                'email' => 'ricky.mandich@gmail.com',
                'password' => Hash::make('R1cc4rd0006'),
            ]
        )->assignRole(Role::findOrCreate('admin'));

        $user->email_verified_at = Carbon::now();
        $user->save();
    }
}
