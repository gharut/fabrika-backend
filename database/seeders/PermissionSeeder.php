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

    }
}
