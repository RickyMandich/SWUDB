<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'cards.import',
            'cards.manage',
            'decks.manage-any',
            'collections.manage-any',
            'users.manage',
            'bot.notifications.receive',
            'mails.test',
            'system.manage-errors',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = Role::findOrCreate('admin');
        $admin->givePermissionTo($permissions);

        $user = User::create(
            [
                'name' => 'RickyMandich',
                'email' => 'ricky.mandich@gmail.com',
                'password' => Hash::make('R1cc4rd0006'),
            ]
        )->assignRole($admin);

        $user->email_verified_at = Carbon::now();
        $user->save();
    }
}
