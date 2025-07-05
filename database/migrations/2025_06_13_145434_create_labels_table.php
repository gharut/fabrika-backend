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
        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('article');
            $table->string('name');
            $table->string('composition');
            $table->string('color');
            $table->boolean('has_chestny_znak')->default(false);
            $table->enum('category', array_map(
                fn(ProductCategory $c) => $c->value,
                ProductCategory::cases()
            ));
            $table->bigInteger('created_by')->constrained('users')->nullOnDelete();
            $table->bigInteger('updated_by')->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('labels');
    }
};
