<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('product_marketplace_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('wb_products')->cascadeOnDelete();
            $table->string('marketplace_code', 50)->index();
            $table->foreignId('marketplace_category_id')->constrained('marketplace_categories')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('product_marketplace_categories');
    }
};
