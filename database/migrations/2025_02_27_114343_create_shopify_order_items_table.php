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
        Schema::create('shopify_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('shopify_orders')->onDelete('cascade');
            $table->string('shopify_line_item_id');
            $table->string('product_id')->nullable();
            $table->string('variant_id')->nullable();
            $table->string('title');
            $table->integer('quantity');
            $table->decimal('price', 10, 2);
            $table->string('sku')->nullable();
            $table->json('raw_data');

            // Indexes for faster queries
            $table->unique(['order_id', 'shopify_line_item_id']);
            $table->index('product_id');
            $table->index('sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopify_order_items');
    }
};
