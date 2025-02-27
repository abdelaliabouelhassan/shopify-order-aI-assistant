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
        Schema::create('shopify_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('inventory_item_id')->unique();
            $table->string('sku')->nullable();
            $table->decimal('cost', 10, 2)->default(0);
            $table->boolean('tracked')->default(false);
            $table->boolean('requires_shipping')->default(true);
            $table->string('variant_id')->nullable();
            $table->json('raw_data');
            $table->timestamp('synced_at');

            // Indexes
            $table->index('sku');
            $table->index('variant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopify_inventory_items');
    }
};
