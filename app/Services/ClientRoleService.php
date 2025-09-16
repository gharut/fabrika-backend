<?php

namespace App\Services;

use App\Models\Client;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ClientRoleService
{
    public function createDefaultRoles(Client $client): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($client->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'api', 'client_id' => $client->id],
            ['visible_name' => 'Администратор']
        );

        $admin->givePermissionTo([
            'view-products', 'create-products', 'edit-products', 'delete-products',
            'view-clients', 'create-clients', 'edit-clients', 'delete-clients',
            'view-marketplace-accounts', 'create-marketplace-accounts',
            'edit-marketplace-accounts', 'delete-marketplace-accounts', 'check-connection',
            'manage-invitations', 'view-cz', 'download-pdf-cz',
            'import-wb-product', 'import-cz', 'defective-cz', 'replace-size-cz',
            'view-product-sizes', 'create-product-sizes', 'edit-product-sizes', 'delete-product-sizes',
            'view-brands', 'create-brands', 'edit-brands', 'delete-brands',
            'list-users', 'get-users',
        ]);

        $manager = Role::firstOrCreate(
            ['name' => 'manager', 'guard_name' => 'api', 'client_id' => $client->id],
            ['visible_name' => 'Менеджер']
        );

        $manager->givePermissionTo([
            'view-products', 'create-products', 'edit-products', 'delete-products',
            'view-clients', 'create-clients', 'edit-clients', 'delete-clients',
            'view-marketplace-accounts', 'create-marketplace-accounts',
            'edit-marketplace-accounts', 'delete-marketplace-accounts', 'check-connection',
            'import-wb-product', 'view-cz', 'download-pdf-cz',
            'import-cz', 'defective-cz', 'replace-size-cz',
            'view-product-sizes', 'create-product-sizes', 'edit-product-sizes', 'delete-product-sizes',
            'view-brands', 'create-brands', 'edit-brands', 'delete-brands',
            'list-users', 'get-users',
        ]);

        $logistics = Role::firstOrCreate(
            ['name' => 'logistics', 'guard_name' => 'api', 'client_id' => $client->id],
            ['visible_name' => 'Логист']
        );

        $logistics->givePermissionTo([
            'view-products', 'view-product-sizes', 'view-brands',
            'view-cz', 'download-pdf-cz',
            'list-users', 'get-users',
        ]);
    }
}
