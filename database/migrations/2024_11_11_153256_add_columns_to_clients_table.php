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
        Schema::table('clients', function (Blueprint $table) {
            $table->string('tin')->nullable();
            $table->string('psrn')->nullable();
            $table->string('account')->nullable();
            $table->string('bank')->nullable();
            $table->string('correspondent_account')->nullable();
            $table->string('bic')->nullable();
            $table->string('legal_address')->nullable();
            $table->decimal('vat', 4, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'tin',
                'psrn',
                'account',
                'bank',
                'correspondent_account',
                'bic',
                'legal_address',
                'vat'
            ]);
        });
    }
};
