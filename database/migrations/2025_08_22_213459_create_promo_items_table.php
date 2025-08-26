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
        Schema::create('promo_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('campaign_id')
                ->constrained('promo_campaigns')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('article', 50);
            $table->unsignedTinyInteger('temp_discount');
            $table->unsignedTinyInteger('snapshot_discount')->nullable();
            $table->dateTime('snapshot_captured_at')->nullable();

            $table->enum('status', ['pending','done','error','skipped', 'reverted'])->default('pending');
            $table->text('error')->nullable();

            $table->timestamps();

            $table->unique(['campaign_id','article']);

            $table->index(['campaign_id','status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_items');
    }
};
