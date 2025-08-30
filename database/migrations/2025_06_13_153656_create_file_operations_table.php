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
        Schema::create('file_operations', function (Blueprint $table) {
            $table->id();

            $table->string('operation_type');
            $table->string('file_name')->nullable();
            $table->string('file_extension')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users');
            
            $table->string('status')->default('in_progress'); 
            $table->text('error_message')->nullable();
            $table->string('related_to')->nullable();
            
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_operations');
    }
};
