<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        // $clientId = 1;
        // app()[\Spatie\Permission\PermissionRegistrar::class]->setPermissionsTeamId($clientId);
        // app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        
        $user = Role::firstOrCreate(
            ['name' => 'user', 'guard_name' => 'api',],
            ['visible_name' => 'Пользователь']
        );

        $admin = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'api',],
            ['visible_name' => 'Администратор']
        );

        $manager = Role::firstOrCreate(
            ['name' => 'manager', 'guard_name' => 'api',],
            ['visible_name' => 'Менеджер']
        );

        $logistics = Role::firstOrCreate(
            ['name' => 'logistics', 'guard_name' => 'api',],
            ['visible_name' => 'Логист']
        );

        $ffAdmin = Role::firstOrCreate(
            ['name' => 'ff-admin', 'guard_name' => 'api',],
            ['visible_name' => 'Администратор фулфилмента']
        );

        $ffPack = Role::firstOrCreate(
            ['name' => 'ff-pack', 'guard_name' => 'api',],
            ['visible_name' => 'Упаковщик фулфилмента']
        );

        $ffViewer = Role::firstOrCreate(
            ['name' => 'ff-viewer', 'guard_name' => 'api',],
            ['visible_name' => 'Просмотр фулфилмента']
        );

        $admin->givePermissionTo([
            'view-products', 'create-products', 'edit-products', 'delete-products',
            'view-labels', 'create-labels', 'edit-labels', 'delete-labels',
            'view-clients', 'create-clients', 'edit-clients', 'delete-clients',
            'view-marketplace-accounts', 'create-marketplace-accounts', 
            'edit-marketplace-accounts', 'delete-marketplace-accounts', 'check-connection',
            'manage-invitations', 'view-cz', 'download-pdf-cz',
            'import-wb-product', 'import-cz', 'defective-cz', 'replace-size-cz',
            'view-product-sizes', 'create-product-sizes', 'edit-product-sizes', 'delete-product-sizes',
            'view-brands', 'create-brands', 'edit-brands', 'delete-brands',
            'list-users', 'get-users', 'edit-client-users', 'delete-client-users',
        ]);
        
        $manager->givePermissionTo([
            'view-products', 'create-products', 'edit-products', 'delete-products',
            'view-labels', 'create-labels', 'edit-labels', 'delete-labels',
            'view-clients', 'create-clients', 'edit-clients', 'delete-clients',
            'view-marketplace-accounts', 'create-marketplace-accounts', 
            'edit-marketplace-accounts', 'delete-marketplace-accounts', 'check-connection',
            'import-wb-product', 'view-cz', 'download-pdf-cz',
            'import-cz', 'defective-cz', 'replace-size-cz',
            'view-product-sizes', 'create-product-sizes', 'edit-product-sizes', 'delete-product-sizes',
            'view-brands', 'create-brands', 'edit-brands', 'delete-brands',
            'list-users', 'get-users',
        ]);
        
        $logistics->givePermissionTo([
            'view-products', 'view-product-sizes', 'view-brands',
            'view-cz', 'download-pdf-cz', 'list-users', 'get-users',
            'view-labels', 'create-labels', 'edit-labels'
        ]);

        $ffAdmin->givePermissionTo([
            'view-products', 'view-product-sizes', 'view-brands', 
            'view-cz', 'download-pdf-cz', 
            'view-labels', 'create-labels', 'edit-labels', 'delete-labels',
            'list-users', 'get-users',
        ]);

        $ffPack->givePermissionTo([
            'view-products', 'view-product-sizes', 'view-brands', 
            'view-cz', 'download-pdf-cz', 
            'view-labels', 'create-labels', 'edit-labels',
        ]);

        $ffViewer->givePermissionTo([
            'view-products', 'view-product-sizes', 'view-brands', 
            'view-cz', 'download-pdf-cz', 
            'view-labels',
        ]);
    }
}
