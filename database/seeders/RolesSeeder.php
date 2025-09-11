<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $user     = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api'], ['visible_name' => 'Пользователь']);

        $admin     = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api'], ['visible_name' => 'Администратор']);
        $manager   = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'api'], ['visible_name' => 'Менеджер']);
        $logistics = Role::firstOrCreate(['name' => 'logistics', 'guard_name' => 'api'], ['visible_name' => 'Логист']);

        $admin->givePermissionTo([
            'view-products', 'create-products', 'edit-products', 'delete-products',
            'view-clients', 'create-clients', 'edit-clients', 'delete-clients',
            'view-marketplace-accounts', 'create-marketplace-accounts', 
            'edit-marketplace-accounts', 'delete-marketplace-accounts',
            'manage-invitations', 'view-cz', 'download-pdf-cz',
            'import-wb-product', 'import-cz', 'defective-cz', 'replace-size-cz'
        ]);
        
        $manager->givePermissionTo([
            'view-products', 'create-products', 'edit-products', 'delete-products',
            'view-clients', 'create-clients', 'edit-clients', 'delete-clients',
            'view-marketplace-accounts', 'create-marketplace-accounts', 
            'edit-marketplace-accounts', 'delete-marketplace-accounts',
            'import-wb-product', 'view-cz', 'download-pdf-cz',
            'import-cz', 'defective-cz', 'replace-size-cz'
        ]);
        
        $logistics->givePermissionTo(['view-products', 'view-cz', 'download-pdf-cz']);
    }
}
