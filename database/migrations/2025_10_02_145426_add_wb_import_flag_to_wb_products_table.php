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
        Schema::table('wb_products', function (Blueprint $table) {
            $table->boolean('is_wb_import')->default(false)->after('has_chestny_znak');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wb_products', function (Blueprint $table) {
            $table->dropColumn('is_wb_import');
        });
    }
};
