<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->string('path')->nullable();
            $table->string('url');

            $table->unsignedInteger('position')->default(0);
            $table->string('type')->default('gallery'); 
            $table->string('alt')->nullable();

            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('storage')->default('local'); 
            $table->timestamps();

            $table->foreignId('product_id')
                ->constrained('wb_products')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
