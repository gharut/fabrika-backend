<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class CreateSuperAdmin extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::create(['name' => 'super-admin', 'visible_name' => "Администратор", 'guard_name' => 'api']);

        $user = \App\Models\User::factory()->create([
             'name' => 'admin',
             'email' => 'admin@test.com',
             'password' => Hash::make("admin")
         ]);

        $user->assignRole($role);
    }
}
