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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->smallInteger('use_consumable')->default(0);
            $table->enum('step', ['PREPROCESSING', 'PROCESSING', 'FINISHING']);
            $table->enum('apply_to', ['ORDER', 'PRODUCT', 'PRODUCT_COUNT', 'PRODUCT_UNIT', 'CUSTOM_COUNT']);
            $table->smallInteger('multiple_products')->default(0);
            $table->string('count_label')->nullable();
            $table->enum("report_type", ['YN', 'COUNT', 'STEPS']);
            $table->float('price');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service');
    }
};
