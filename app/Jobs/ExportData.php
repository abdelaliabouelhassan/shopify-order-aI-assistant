<?php

namespace App\Jobs;

use App\Models\ShopifyOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExportData implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $publicPath = public_path();
        $ordersFile = fopen($publicPath . '/shopify_orders.csv', 'w');
        fputcsv($ordersFile, [
            'order_number',
            'email',
            'created_at',
            'updated_at',
            'financial_status',
            'fulfillment_status',
            'total_price',
            'total_tax',
            'tags',
            'province',
            'province_code',
            'city',
            'zip',
            'address1',
            'Lineitem name',
            'Lineitem price',
            'Lineitem sku',
            'Lineitem quantity',
            'vendor'
        ]);

        // Process and export orders
        ShopifyOrder::chunk(100, function ($orders) use ($ordersFile) {
            foreach ($orders as $order) {
                $order->load('orderItems');
                $shippingAddress = json_decode($order->shipping_address, true);

                // Extract base order data
                $baseOrderData = [
                    $order->order_number,
                    $order->email,
                    $order->created_at->toDateTimeString(),
                    $order->updated_at->toDateTimeString(),
                    $order->financial_status,
                    $order->fulfillment_status,
                    (float)$order->total_price,
                    (float)$order->total_tax,
                    $order->tags ?: '',
                    $shippingAddress['province'] ?? '',
                    $shippingAddress['province_code'] ?? '',
                    $shippingAddress['city'] ?? '',
                    $shippingAddress['zip'] ?? '',
                    $shippingAddress['address1'] ?? '',

                ];

                // If no items, write just the order
                if ($order->orderItems->isEmpty()) {
                    $emptyLineItem = array_merge($baseOrderData, ['', '', '', '', '']);
                    fputcsv($ordersFile, $emptyLineItem);
                }

                // Process each line item
                $isFirstItem = true;
                foreach ($order->orderItems as $item) {
                    // For the first item, include total price and tax
                    // For subsequent items, make them empty
                    $totalPrice = $isFirstItem ? (float)$order->total_price : '';
                    $totalTax = $isFirstItem ? (float)$order->total_tax : '';

                    // Add line item details to order row
                    $lineItemData = array_merge(
                        array_slice($baseOrderData, 0, 6), // Order details before total_price
                        [$totalPrice, $totalTax], // Include price and tax only for first item
                        array_slice($baseOrderData, 8), // Rest of order details after total_tax
                        [
                            $item->title,
                            (float)$item->price,
                            $item->sku,
                            (int)$item->quantity,
                            $item->vendor
                        ]
                    );

                    fputcsv($ordersFile, $lineItemData);
                    $isFirstItem = false;
                }

                // Free up memory
                $order->unsetRelation('orderItems');
            }

            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        });

        fclose($ordersFile);

        // Create products CSV file with header - separate process
        $productsFile = fopen($publicPath . '/shopify_products.csv', 'w');
        fputcsv($productsFile, [
            'sku',
            'cost',
            'tracked',
            'requires_shipping',
            'available'
        ]);

        // Query all inventory items with their inventory levels
        $query = DB::table('shopify_inventory_items as items')
            ->leftJoin('shopify_inventory_levels as levels', 'items.inventory_item_id', '=', 'levels.inventory_item_id')
            ->select([
                'items.sku',
                'items.cost',
                'items.tracked',
                'items.requires_shipping',
                DB::raw('COALESCE(levels.available, 0) as available')
            ])
            ->whereNotNull('items.sku')
            ->orderBy('items.sku');

        // Process in chunks for memory efficiency
        $query->chunk(500, function ($inventoryItems) use ($productsFile) {
            foreach ($inventoryItems as $item) {
                fputcsv($productsFile, [
                    $item->sku,
                    (float)$item->cost,
                    $item->tracked ? 'yes' : 'no',
                    $item->requires_shipping ? 'yes' : 'no',
                    (int)$item->available
                ]);
            }

            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        });

        fclose($productsFile);

        Artisan::call('shopify:update-ai');

        // return response()->json([
        //     'success' => true,
        //     'message' => 'Export completed successfully. Files: shopify_orders.csv and shopify_products.csv'
        // ]);
    }
}
