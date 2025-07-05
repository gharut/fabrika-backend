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
        Schema::create('chestny_znak_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('size_id')->constrained('product_sizes')->restrictOnDelete();
            $table->string('code')->unique();
            $table->boolean('used')->default(false);
            $table->foreignId('used_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chestny_znak_labels');
    }
};
