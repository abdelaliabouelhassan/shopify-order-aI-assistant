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
        Schema::table('shopify_orders', function (Blueprint $table) {
            //
            $table->string('province')->nullable();
            $table->string('province_code')->nullable();
            $table->string('city')->nullable();
            $table->string('zip')->nullable();
            $table->string('address1')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shopify_orders', function (Blueprint $table) {
            $table->dropColumn(['province', 'province_code', 'city', 'zip', 'address1']);
        });
    }
};
