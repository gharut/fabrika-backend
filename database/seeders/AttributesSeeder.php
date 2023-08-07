<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\ProductAttribute;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Permission;

class AttributesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // create permissions for suppliers
        Attribute::create(['name' => 'Размер']);
        Attribute::create(['name' => 'Цвет']);

    }
}
