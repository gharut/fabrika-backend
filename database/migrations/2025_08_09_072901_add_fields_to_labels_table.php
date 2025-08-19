<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\SizeDisplayType;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('labels', function (Blueprint $table) {
            $table->string('client_name')->nullable()->after('name');
            $table->foreignId('printer_id')
                ->nullable()
                ->constrained('printers')
                ->nullOnDelete()
                ->after('client_name');
            $table->foreignId('label_template_id')
                ->nullable()
                ->constrained('label_templates')
                ->nullOnDelete()
                ->after('printer_id');
            $table->boolean('print_single_ean13')->default(false);
            $table->boolean('print_double_ean13')->default(false);
            $table->boolean('duplicate_chz')->default(false);
            $table->string('size_display_type')
                ->default(SizeDisplayType::RUS->value)
                ->after('duplicate_chz');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('labels', function (Blueprint $table) {
            $table->dropForeign(['printer_id', 'label_template_id']);
            $table->dropColumn([
                'client_name',
                'printer_id',
                'label_template_id',
                'print_single_ean13',
                'print_double_ean13',
                'duplicate_chz',
                'size_display_type'
            ]);
        });
    }
};
