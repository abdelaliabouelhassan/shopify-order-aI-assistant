<?php

namespace App\Http\Controllers;

use App\Models\ShopifyInventoryItem;
use App\Models\ShopifyOrder;
use App\Services\ShopifyOrderAnalyst;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiAssistantController extends Controller
{
    //
    // Example controller method to create/update an assistant
    public function setupAssistant(Request $request)
    {

        // return $filePath = $request->file('knowledge_file')->path();
        $filePath1 = public_path('shopify_orders.csv');
        $filePath2 = public_path('shopify_products.csv');

        // Create a new analyst instance
        $analyst = new ShopifyOrderAnalyst;

        // Setup a new assistant with the file
        $analyst->setupNewAssistant([$filePath1, $filePath2]);

        return response()->json([
            'success' => true,
            'file_id' => $analyst->getFileId(),
            'assistant_id' => $analyst->getAssistantId()
        ]);
    }

    // Example controller method to update knowledge
    public function updateKnowledge(Request $request)
    {

        $filePath1 = public_path('shopify_orders.csv');
        $filePath2 = public_path('shopify_products.csv');


        // Get existing file_id and assistant_id from database
        $assistantData = DB::table('ai_assistants')

            ->where('type', 'shopify_analyst')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$assistantData) {
            return response()->json(['error' => 'No assistant found'], 404);
        }

        // Initialize with existing IDs
        $analyst = new ShopifyOrderAnalyst(
            $assistantData->file_id,
            $assistantData->assistant_id
        );

        // Update with new knowledge file
        $analyst->updateKnowledge([$filePath1, $filePath2]);

        return response()->json([
            'success' => true,
            'file_id' => $analyst->getFileId(),
            'assistant_id' => $analyst->getAssistantId()
        ]);
    }

    // Example controller method to ask a question
    public function askQuestion(Request $request)
    {

        $question = $request->input('question');

        // Get existing assistant data
        $assistantData = DB::table('ai_assistants')
            ->where('type', 'shopify_analyst')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$assistantData) {
            return response()->json(['error' => 'No assistant found'], 404);
        }

        // Initialize with existing IDs
        $analyst = new ShopifyOrderAnalyst(
            $assistantData->file_id,
            $assistantData->assistant_id
        );

        // Ask the question
        $response = $analyst->ask($question);

        return response()->json([
            'success' => true,
            'response' => $response
        ]);
    }




    // public function export()
    // {
    //     // Create orders CSV file with header
    //     $ordersFile = fopen('shopify_orders.csv', 'w');
    //     fputcsv($ordersFile, [
    //         'order_number',
    //         'email',
    //         'created_at',
    //         'updated_at',
    //         'financial_status',
    //         'fulfillment_status',
    //         'total_price',
    //         'total_tax',
    //         'tags',
    //         'province',
    //         'province_code',
    //         'city',
    //         'zip',
    //         'address1',
    //         'Lineitem name',
    //         'Lineitem price',
    //         'Lineitem sku',
    //         'Lineitem quantity',
    //         'vendor'
    //     ]);

    //     // Process and export orders
    //     ShopifyOrder::chunk(100, function ($orders) use ($ordersFile) {
    //         foreach ($orders as $order) {
    //             $order->load('orderItems');

    //             // Extract base order data
    //             $baseOrderData = [
    //                 $order->order_number,
    //                 $order->email,
    //                 $order->created_at->toDateTimeString(),
    //                 $order->updated_at->toDateTimeString(),
    //                 $order->financial_status,
    //                 $order->fulfillment_status,
    //                 (float)$order->total_price,
    //                 (float)$order->total_tax,
    //                 $order->tags ?: '',
    //                 $order->shipping_address['province'] ?? '',
    //                 $order->shipping_address['province_code'] ?? '',
    //                 $order->shipping_address['city'] ?? '',
    //                 $order->shipping_address['zip'] ?? '',
    //                 $order->shipping_address['address1'] ?? '',
    //             ];

    //             // If no items, write just the order
    //             if ($order->orderItems->isEmpty()) {
    //                 $emptyLineItem = array_merge($baseOrderData, ['', '', '', '', '']);
    //                 fputcsv($ordersFile, $emptyLineItem);
    //             }

    //             // Process each line item
    //             $isFirstItem = true;
    //             foreach ($order->orderItems as $item) {
    //                 // For the first item, include total price and tax
    //                 // For subsequent items, make them empty
    //                 $totalPrice = $isFirstItem ? (float)$order->total_price : '';
    //                 $totalTax = $isFirstItem ? (float)$order->total_tax : '';

    //                 // Add line item details to order row
    //                 $lineItemData = array_merge(
    //                     array_slice($baseOrderData, 0, 6), // Order details before total_price
    //                     [$totalPrice, $totalTax], // Include price and tax only for first item
    //                     array_slice($baseOrderData, 8), // Rest of order details after total_tax
    //                     [
    //                         $item->title,
    //                         (float)$item->price,
    //                         $item->sku,
    //                         (int)$item->quantity,
    //                         $item->vendor
    //                     ]
    //                 );

    //                 fputcsv($ordersFile, $lineItemData);
    //                 $isFirstItem = false;
    //             }

    //             // Free up memory
    //             $order->unsetRelation('orderItems');
    //         }

    //         if (function_exists('gc_collect_cycles')) {
    //             gc_collect_cycles();
    //         }
    //     });

    //     fclose($ordersFile);

    //     // Create products CSV file with header - separate process
    //     $productsFile = fopen('shopify_products.csv', 'w');
    //     fputcsv($productsFile, [
    //         'sku',
    //         'cost',
    //         'tracked',
    //         'requires_shipping',
    //         'available'
    //     ]);

    //     // Query all inventory items with their inventory levels
    //     $query = DB::table('shopify_inventory_items as items')
    //         ->leftJoin('shopify_inventory_levels as levels', 'items.inventory_item_id', '=', 'levels.inventory_item_id')
    //         ->select([
    //             'items.sku',
    //             'items.cost',
    //             'items.tracked',
    //             'items.requires_shipping',
    //             DB::raw('COALESCE(levels.available, 0) as available')
    //         ])
    //         ->whereNotNull('items.sku')
    //         ->orderBy('items.sku');

    //     // Process in chunks for memory efficiency
    //     $query->chunk(500, function ($inventoryItems) use ($productsFile) {
    //         foreach ($inventoryItems as $item) {
    //             fputcsv($productsFile, [
    //                 $item->sku,
    //                 (float)$item->cost,
    //                 $item->tracked ? 'yes' : 'no',
    //                 $item->requires_shipping ? 'yes' : 'no',
    //                 (int)$item->available
    //             ]);
    //         }

    //         if (function_exists('gc_collect_cycles')) {
    //             gc_collect_cycles();
    //         }
    //     });

    //     fclose($productsFile);

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Export completed successfully. Files: shopify_orders.csv and shopify_products.csv'
    //     ]);
    // }
}
