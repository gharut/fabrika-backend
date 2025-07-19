<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\ProductCategory;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wb_products', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('client_id')->constrained('clients')->onDelete('restrict');
            $table->string('name');
            $table->string('color')->nullable();
            $table->string('article');
            $table->string('composition')->nullable();
            $table->boolean('has_chestny_znak')->default(false);
            
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('updated_by')->constrained('users')->onDelete('restrict');
            $table->enum(
                'category',
                array_map(fn(ProductCategory $c) => $c->value, ProductCategory::cases())
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wb_products');
    }
};
