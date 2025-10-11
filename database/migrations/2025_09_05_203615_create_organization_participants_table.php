<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('clients')->cascadeOnDelete();

            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_participants');
    }
};
