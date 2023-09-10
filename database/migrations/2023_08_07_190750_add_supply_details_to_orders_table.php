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
        Schema::table('orders', function (Blueprint $table) {
            $table->json("supply_details")->after("supply_time")
                ->comment("{[name:'asdf',phone:'2123',load_number:'12121', 'other_details':'asdfasdf', images:['asdfasdf.jpg','asdfasdf.jpg']}");
            $table->json("supply_items")->after("supply_time")
                ->comment("{{item:[{qty:12,height:123,width:123,length:123,unit:'meter',weight:10]...}],}");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn("supply_details");
            $table->dropColumn("supply_items");
        });
    }
};
