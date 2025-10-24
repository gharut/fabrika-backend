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
        Schema::create('strategy_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('strategy_id')
                  ->constrained('pricing_strategies')
                  ->cascadeOnDelete();

            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->string('status')->default('active');

            $table->decimal('temp_discount', 5, 2)->nullable();
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('strategy_items');
    }
};
