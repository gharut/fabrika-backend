<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Client;
use App\Models\OrganizationParticipant;
use App\Models\Role;
use App\Models\SystemAccount;
use App\Enums\ClientTypes;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class BootstrapAdminSeeder extends Seeder
{
    public function run(): void
    {   
        $admin = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'api',],
            ['visible_name' => 'Администратор']
        );

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

        OrganizationParticipant::firstOrCreate([
            'organization_id' => $client->id,
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);


        app(PermissionRegistrar::class)->setPermissionsTeamId($client->id);
        $user->assignRole('admin');

        SystemAccount::firstOrCreate(
            ['user_id' => $user->id],
            [
                'name' => 'Главный супер-админ',
                'type' => 'human',
                'role' => 'super-admin',
                'notes' => 'Первичный системный администратор платформы',
                'created_by' => $user->id,
            ]
        );
    }
}
