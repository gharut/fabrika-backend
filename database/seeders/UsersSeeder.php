<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\Client;
use App\Enums\ClientTypes;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin2@test.com',
            'password' => Hash::make("admin")
        ]);
        $admin->assignRole('admin');

        $manager = User::factory()->create([
            'name' => 'Manager', 
            'email' => 'manager@test.com',
            'password' => Hash::make("manager")
        ]);
        $manager->assignRole('manager');

        $logistics = User::factory()->create([
            'name' => 'Logistics',
            'email' => 'logistics@test.com', 
            'password' => Hash::make("logistics")
        ]);
        $logistics->assignRole('logistics');

        Client::create([
            'name' => 'FFabrika',
            'phone'=> '',
            'email'=> '',
            'type' => ClientTypes::LEGAL_ENTITY,
        ]);
    }
}
