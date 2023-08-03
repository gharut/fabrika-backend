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
        Schema::create('service_attributes', function (Blueprint $table) {
            $table->id();
            $table->bigInteger("service_id");
            $table->string("name");
            $table->enum("attribute_type", ['CUSTOM_LIST', 'YN', 'PRODUCT_LIST']);
            $table->smallInteger("allow_multiselect")->default(0);
            $table->smallInteger("apply_to_price_type")->default(0)->comment("0->not changing, 1->increments, 2->decrements, 3->changes service price");
            $table->json('attribute_data')->comment("{[]}");
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_attributes');
    }
};
