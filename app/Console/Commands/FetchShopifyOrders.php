<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\ShopifyService;
use Illuminate\Console\Command;

class FetchShopifyOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-shopify-orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch orders from Shopify';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $orders = (new ShopifyService())->getOrders();

        foreach ($orders as $shopifyOrder) {
            Order::updateOrCreate(
                ['shopify_order_id' => $shopifyOrder['id']],
                [
                    'customer_name' => $shopifyOrder['customer']['name'] ?? 'Unknown',
                    'items' => json_encode($shopifyOrder['line_items']),
                    'total_price' => $shopifyOrder['total_price'],
                    'shipping_address' => json_encode($shopifyOrder['shipping_address']),
                    'order_data' => json_encode($shopifyOrder)
                ]
            );
        }
    }
}
