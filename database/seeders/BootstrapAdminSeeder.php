<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Role;
use App\Enums\ClientTypes;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class BootstrapAdminSeeder extends Seeder
{
    public function run(): void
    {
        $client = Client::create([
            'name' => 'FFabrika',
            'phone'=> '',
            'email'=> '',
            'type' => ClientTypes::LEGAL_ENTITY,
        ]);

        $user = User::firstOrCreate(
            ['email' => 'admin@test.com'],
            ['name' => 'Super Admin', 'password' => Hash::make('admin')]
        );

        ClientUser::firstOrCreate(
            ['client_id' => $client->id, 'user_id' => $user->id],
        );

        $role = Role::firstOrCreate(['name' => 'super-admin', 'visible_name' => 'Суперадминистратор', 'guard_name' => 'api']);

        app(PermissionRegistrar::class)->setPermissionsTeamId($client->id);
        $user->assignRole($role);

        // Удобство — запомним последний выбранный client
        // $user->forceFill(['last_selected_client_id' => $client->id])->save();
    }
}
