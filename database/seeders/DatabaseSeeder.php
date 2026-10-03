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

        $admin = config('seed.admin');

        if (! $admin['email'] || ! $admin['password']) {
            $this->command->warn('SEED_ADMIN_EMAIL / SEED_ADMIN_PASSWORD non impostate: utente admin non creato.');

            return;
        }

        $user = User::updateOrCreate(
            ['email' => $admin['email']],
            [
                'name' => $admin['name'],
                'password' => Hash::make($admin['password']),
            ]
        );
        $user->forceFill(['email_verified_at' => Carbon::now()])->save();
        $user->assignRole(Role::findOrCreate('admin'));

        $user->email_verified_at = Carbon::now();
        $user->save();
    }
}
