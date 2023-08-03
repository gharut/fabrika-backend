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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->string("address")->nullable();
            $table->string("website")->nullable();
            $table->json("contacts")->nullable()->comment("[{name, position, phone},{name,position,phone},...]");
            $table->json("payments")->nullable()->comment("[{type, details},{type,details},...]");;
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
