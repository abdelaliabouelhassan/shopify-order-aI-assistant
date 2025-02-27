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
        Schema::create('shopify_inventory_levels', function (Blueprint $table) {
            $table->id();
            $table->string('inventory_item_id');
            $table->integer('available')->default(0);
            $table->timestamp('updated_at');
            $table->timestamp('synced_at');

            // Indexes
            $table->unique(['inventory_item_id', 'location_id']);
            $table->index('inventory_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopify_inventory_levels');
    }
};
