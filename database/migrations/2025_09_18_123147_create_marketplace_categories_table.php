<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('marketplace_categories', function (Blueprint $table) {
            $table->id();
            $table->string('marketplace_code', 50)->index();
            $table->string('external_id', 64)->nullable();
            $table->string('name', 255);
            $table->foreignId('parent_id')->nullable()->constrained('marketplace_categories')->nullOnDelete();
            $table->string('parent_external_id', 64)->nullable()->index();

            $table->unique(['marketplace_code','external_id'], 'u_mpcode_external');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('marketplace_categories');
    }
};
