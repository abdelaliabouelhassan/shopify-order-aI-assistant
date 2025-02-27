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
        Schema::create('shopify_orders', function (Blueprint $table) {
            $table->id();
            $table->string('shopify_id')->unique();
            $table->string('order_number');
            $table->string('email')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->string('financial_status')->nullable();
            $table->string('fulfillment_status')->nullable();
            $table->decimal('total_price', 10, 2);
            $table->decimal('total_tax', 10, 2)->default(0);
            $table->string('currency', 3);
            $table->text('tags')->nullable();
            $table->json('customer_data')->nullable();
            $table->json('shipping_address')->nullable();
            $table->json('billing_address')->nullable();
            $table->json('raw_data');
            $table->timestamp('synced_at');

            // Indexes for faster queries
            $table->index('order_number');
            $table->index('financial_status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopify_orders');
    }
};
