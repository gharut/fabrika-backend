<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_levels', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->nullable()
                  ->constrained('wb_products')->nullOnDelete();
            $table->foreignId('size_id')
                  ->constrained('product_sizes')->cascadeOnDelete();

            $table->string('source', 32);

            $table->unsignedInteger('qty')->default(0);
            $table->timestamp('synced_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_levels');
    }
};