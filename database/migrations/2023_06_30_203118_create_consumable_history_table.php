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
        Schema::create('consumable_history', function (Blueprint $table) {
            $table->id();
            $table->smallInteger("type")->comment('0->out, 1->in');
            $table->bigInteger("consumable_id");
            $table->bigInteger("supplier_id")->nullable();
            $table->bigInteger("operation_id")->nullable();
            $table->integer("qty");
            $table->float("price_per_unit")->nullable();
            $table->smallInteger("delivery_status")->nullable()->comment("0->not delivered, 1->delivered");
            $table->smallInteger("payment_status",)->nullable()->comment("0->not paid, 1->paid");
            $table->timestamp("delivery_date",)->nullable();
            $table->string("details",)->nullable();
            $table->timestamp("payment_date",)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consumable_history');
    }
};
