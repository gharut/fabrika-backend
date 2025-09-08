<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // create permissions for suppliers
        Permission::create(['name' => 'list-suppliers', 'visible_name' => 'Страница поставщиков', 'category' => 'SUPPLIER']);
        Permission::create(['name' => 'get-suppliers', 'visible_name' => 'Просматривать поставщика', 'category' => 'SUPPLIER']);
        Permission::create(['name' => 'create-suppliers', 'visible_name' => 'Создавать поставщика', 'category' => 'SUPPLIER']);
        Permission::create(['name' => 'edit-suppliers', 'visible_name' => 'Редактировать поставщика', 'category' => 'SUPPLIER']);
        Permission::create(['name' => 'delete-suppliers', 'visible_name' => 'Удалять поставщика', 'category' => 'SUPPLIER']);

        // create permissions for tags
        Permission::create(['name' => 'list-tags', 'visible_name' => 'Страница категорий', 'category' => 'TAGS']);
        Permission::create(['name' => 'get-tags', 'visible_name' => 'Просматривать категории', 'category' => 'TAGS']);
        Permission::create(['name' => 'create-tags', 'visible_name' => 'Создавать категории', 'category' => 'TAGS']);
        Permission::create(['name' => 'edit-tags', 'visible_name' => 'Редактировать категории', 'category' => 'TAGS']);
        Permission::create(['name' => 'delete-tags', 'visible_name' => 'Удалять категории', 'category' => 'TAGS']);

        // create permissions for Users
        Permission::create(['name' => 'list-users', 'visible_name' => 'Страница пользователей', 'category' => 'USERS']);
        Permission::create(['name' => 'get-users', 'visible_name' => 'Просматривать пользователя', 'category' => 'USERS']);
        Permission::create(['name' => 'create-users', 'visible_name' => 'Создавать пользователя', 'category' => 'USERS']);
        Permission::create(['name' => 'edit-users', 'visible_name' => 'Редактировать пользователя', 'category' => 'USERS']);
        Permission::create(['name' => 'delete-users', 'visible_name' => 'Удалять пользователя', 'category' => 'USERS']);

        // create permissions for Roles
        Permission::create(['name' => 'list-roles', 'visible_name' => 'Страница ролей', 'category' => 'ROLES']);
        Permission::create(['name' => 'get-roles', 'visible_name' => 'Просматривать роли', 'category' => 'ROLES']);
        Permission::create(['name' => 'create-roles', 'visible_name' => 'Создавать роли', 'category' => 'ROLES']);
        Permission::create(['name' => 'edit-roles', 'visible_name' => 'Редактировать роли', 'category' => 'ROLES']);
        Permission::create(['name' => 'delete-roles', 'visible_name' => 'Удалять роли', 'category' => 'ROLES']);
        
        // create permissions for Users
        Permission::create(['name' => 'view-clients', 'visible_name' => 'Просматривать клиента', 'category' => 'CLIENTS']);
        Permission::create(['name' => 'create-clients', 'visible_name' => 'Создавать клиента', 'category' => 'CLIENTS']);
        Permission::create(['name' => 'edit-clients', 'visible_name' => 'Редактировать клиента', 'category' => 'CLIENTS']);
        Permission::create(['name' => 'delete-clients', 'visible_name' => 'Удалять клиента', 'category' => 'CLIENTS']);

        // create permissions for Product
        Permission::create(['name' => 'view-products', 'visible_name' => 'Просматривать товар', 'category' => 'PRODUCTS']);
        Permission::create(['name' => 'edit-products', 'visible_name' => 'Редактировать товар', 'category' => 'PRODUCTS']);
        Permission::create(['name' => 'create-products', 'visible_name' => 'Создавать товар', 'category' => 'PRODUCTS']);
        Permission::create(['name' => 'delete-products', 'visible_name' => 'Удалять товар', 'category' => 'PRODUCTS']);

        // create permissions for MarketplaceAccount
        Permission::create(['name' => 'view-marketplace-accounts', 'visible_name' => 'Просматривать подключения к маркетплейсам', 'category' => 'MARKETPLACE_ACCOUNTS']);
        Permission::create(['name' => 'edit-marketplace-accounts', 'visible_name' => 'Редактировать подключения к маркетплейсам', 'category' => 'MARKETPLACE_ACCOUNTS']);
        Permission::create(['name' => 'create-marketplace-accounts', 'visible_name' => 'Создавать подключения к маркетплейсам', 'category' => 'MARKETPLACE_ACCOUNTS']);
        Permission::create(['name' => 'delete-marketplace-accounts', 'visible_name' => 'Удалять подключения к маркетплейсам', 'category' => 'MARKETPLACE_ACCOUNTS']);
        
        // create permissions for Invitations
        Permission::create(['name' => 'manage-invitations', 'visible_name' => 'Управлять приглашениями', 'category' => 'INVITATIONS']);
        
        // create permissions for WB
        Permission::create(['name' => 'import-wb-product', 'visible_name' => 'Импорт товаров с WB', 'category' => 'WB']);

        // create permissions for CZ
        Permission::create(['name' => 'view-cz', 'visible_name' => 'Просмотр ЧЗ', 'category' => 'CHESTNY_ZNAK']);
        Permission::create(['name' => 'import-cz', 'visible_name' => 'Импорт ЧЗ', 'category' => 'CHESTNY_ZNAK']);
        Permission::create(['name' => 'download-pdf-cz', 'visible_name' => 'Скачать PDF этикетки', 'category' => 'CHESTNY_ZNAK']);
        Permission::create(['name' => 'defective-cz', 'visible_name' => 'Списание дефектных ЧЗ', 'category' => 'CHESTNY_ZNAK']);
        Permission::create(['name' => 'replace-size-cz', 'visible_name' => 'Замена размера ЧЗ', 'category' => 'CHESTNY_ZNAK']);
    }
}
