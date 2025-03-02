<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ShopifyService
{
    protected $domain;
    protected $apiVersion;
    protected $accessToken;
    protected $baseUrl;
    protected $ignoredLocationIds;

    public function __construct()
    {
        $this->domain = env('SHOPIFY_DOMAIN');
        $this->apiVersion = env('SHOPIFY_API_VERSION', '2024-01');
        $this->accessToken = env('SHOPIFY_API_SECRET');
        $this->baseUrl = "https://{$this->domain}/admin/api/{$this->apiVersion}";
        $this->ignoredLocationIds = explode(',', env('IGNORED_LOCATION_IDS', ''));
    }

    /**
     * Get all orders using pagination
     * Handles large datasets (1000k+ orders)
     */
    public function getAllOrders()
    {
        try {
            $allOrders = [];
            $nextPageUrl = "{$this->baseUrl}/orders.json?limit=250&status=any";
            $totalProcessed = 0;

            // Start a database transaction for bulk operations
            DB::beginTransaction();

            do {
                Log::info("Fetching orders page: " . $nextPageUrl);

                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => $this->accessToken
                ])->get($nextPageUrl);

                if (!$response->successful()) {
                    Log::error("Failed to fetch orders: " . $response->body());
                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => "API Error: " . $response->status()
                    ];
                }

                $data = $response->json();
                $orders = $data['orders'] ?? [];
                $count = count($orders);

                if ($count > 0) {
                    // Process and save this batch of orders
                    $this->saveOrdersBatch($orders);
                    $totalProcessed += $count;

                    // Commit every 1000 orders to avoid transaction timeouts
                    if ($totalProcessed % 1000 === 0) {
                        DB::commit();
                        DB::beginTransaction();
                        Log::info("Processed $totalProcessed orders so far");
                    }
                }

                // Get link header for pagination
                $linkHeader = $response->header('Link');
                $nextPageUrl = $this->getNextPageUrl($linkHeader);

                // Avoid rate limits
                if ($nextPageUrl) {
                    usleep(500000); // 500ms delay
                }
            } while ($nextPageUrl && $count > 0);

            // Commit any remaining changes
            DB::commit();

            // Update last sync time
            Cache::put('shopify_orders_last_sync', now(), now()->addDay());

            return [
                'success' => true,
                'count' => $totalProcessed,
                'message' => "Successfully synced $totalProcessed orders"
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error syncing orders: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Extract next page URL from Link header
     */
    private function getNextPageUrl($linkHeader)
    {
        if (!$linkHeader) {
            return null;
        }

        // Link header format: <https://...>; rel="next", <https://...>; rel="previous"
        $links = explode(',', $linkHeader);

        foreach ($links as $link) {
            if (strpos($link, 'rel="next"') !== false) {
                preg_match('/<(.*?)>/', $link, $matches);
                return $matches[1] ?? null;
            }
        }

        return null;
    }

    /**
     * Save a batch of orders to the database
     */
    private function saveOrdersBatch($orders)
    {
        foreach ($orders as $orderData) {
            // Check if order exists to avoid duplicates
            $existingOrder = DB::table('shopify_orders')
                ->where('shopify_id', $orderData['id'])
                ->first();

            $orderDetails = [
                'shopify_id' => $orderData['id'],
                'order_number' => $orderData['order_number'] ?? '',
                'email' => $orderData['email'] ?? null,
                'created_at' => $orderData['created_at'],
                'updated_at' => $orderData['updated_at'],
                'financial_status' => $orderData['financial_status'] ?? null,
                'fulfillment_status' => $orderData['fulfillment_status'] ?? null,
                'total_price' => $orderData['total_price'] ?? 0,
                'total_tax' => $orderData['total_tax'] ?? 0,
                'currency' => $orderData['currency'] ?? 'USD',
                'tags' => $orderData['tags'] ?? null,
                // Add the new address fields you requested
                'province' => $orderData['shipping_address']['province'] ?? null,
                'province_code' => $orderData['shipping_address']['province_code'] ?? null,
                'city' => $orderData['shipping_address']['city'] ?? null,
                'zip' => $orderData['shipping_address']['zip'] ?? null,
                'address1' => $orderData['shipping_address']['address1'] ?? null,
                'customer_data' => json_encode($orderData['customer'] ?? []),
                'shipping_address' => json_encode($orderData['shipping_address'] ?? []),
                'billing_address' => json_encode($orderData['billing_address'] ?? []),
                'raw_data' => json_encode($orderData), // Full data for AI analysis
                'synced_at' => now(),
            ];

            if ($existingOrder) {
                // Update existing order
                DB::table('shopify_orders')
                    ->where('shopify_id', $orderData['id'])
                    ->update($orderDetails);

                // Delete existing line items
                DB::table('shopify_order_items')
                    ->where('order_id', $existingOrder->id)
                    ->delete();

                $orderDbId = $existingOrder->id;
            } else {
                // Insert new order
                $orderDbId = DB::table('shopify_orders')->insertGetId($orderDetails);
            }

            // Save line items
            if (isset($orderData['line_items']) && is_array($orderData['line_items'])) {
                foreach ($orderData['line_items'] as $item) {
                    DB::table('shopify_order_items')->insert([
                        'order_id' => $orderDbId,
                        'shopify_line_item_id' => $item['id'],
                        'product_id' => $item['product_id'] ?? null,
                        'variant_id' => $item['variant_id'] ?? null,
                        'title' => $item['title'] ?? '',
                        'quantity' => $item['quantity'] ?? 0,
                        'price' => $item['price'] ?? 0,
                        'sku' => $item['sku'] ?? null,
                        'vendor' => $item['vendor'] ?? null, // Added vendor to each line item
                        'raw_data' => json_encode($item),
                    ]);
                }
            }
        }
    }

    /**
     * Sync recent orders (for regular updates)
     */
    public function syncRecentOrders($days = 2)
    {
        $url = "{$this->baseUrl}/orders.json";
        $createdAtMin = now()->subDays($days)->toIso8601String();

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->accessToken
        ])->get($url, [
            'limit' => 250,
            'status' => 'any',
            'created_at_min' => $createdAtMin
        ]);

        if (!$response->successful()) {
            return [
                'success' => false,
                'message' => "API Error: " . $response->status()
            ];
        }

        $data = $response->json();
        $this->saveOrdersBatch($data['orders'] ?? []);

        return [
            'success' => true,
            'count' => count($data['orders'] ?? []),
            'message' => "Synced recent orders successfully"
        ];
    }

    /**
     * Get all inventory items
     */
    public function getAllInventory()
    {
        try {
            $variants = $this->getAllProductVariants();
            $totalProcessed = 0;
            $inventoryItemIds = [];

            // Extract inventory_item_ids from variants
            foreach ($variants as $variant) {
                if (isset($variant['inventory_item_id'])) {
                    $inventoryItemIds[] = $variant['inventory_item_id'];
                }
            }

            // Get inventory item details in batches
            $inventoryItems = $this->getInventoryItemDetails($inventoryItemIds);

            // Save inventory items
            if (!empty($inventoryItems)) {
                $this->saveInventoryBatch($inventoryItems);
            }

            DB::beginTransaction();

            foreach ($variants as $variant) {
                // Update inventory levels (ignore location)
                DB::table('shopify_inventory_levels')->updateOrInsert(
                    [
                        'inventory_item_id' => (string)$variant['inventory_item_id']
                    ],
                    [
                        'available' => $variant['inventory_quantity'] ?? 0,
                        'updated_at' => now(),
                        'synced_at' => now(),
                    ]
                );
                $totalProcessed++;
            }

            DB::commit();

            return [
                'success' => true,
                'count' => $totalProcessed,
                'message' => "Synced $totalProcessed inventory levels and " . count($inventoryItems) . " inventory items"
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error syncing inventory: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Get inventory item details from Shopify
     */
    private function getInventoryItemDetails($inventoryItemIds)
    {

        Log::info('testing', [$inventoryItemIds]);
        $allItems = [];

        // Process in batches of 50 to avoid URL length limits
        $chunks = array_chunk($inventoryItemIds, 50);

        foreach ($chunks as $chunk) {
            $ids = implode(',', $chunk);
            Log::alert('test one');
            $url = "{$this->baseUrl}/inventory_items.json?ids={$ids}";

            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken
            ])->get($url);

            if (!$response->successful()) {
                Log::error("Failed to fetch inventory items: " . $response->body());
                continue;
            }

            $data = $response->json();
            if (isset($data['inventory_items']) && is_array($data['inventory_items'])) {
                $allItems = array_merge($allItems, $data['inventory_items']);
            }

            // Avoid rate limits
            usleep(500000); // 500ms delay
        }
        Log::alert('final test one', [$allItems]);
        return $allItems;
    }

    /**
     * Get all product variants to extract inventory_item_ids
     */
    private function getAllProductVariants()
    {
        $allVariants = [];
        $nextPageUrl = "{$this->baseUrl}/products.json?limit=250";

        do {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken
            ])->get($nextPageUrl);

            if (!$response->successful()) {
                Log::error("Failed to fetch products: " . $response->body());
                return [];
            }

            $data = $response->json();
            $products = $data['products'] ?? [];

            foreach ($products as $product) {
                if (isset($product['variants']) && is_array($product['variants'])) {
                    foreach ($product['variants'] as $variant) {
                        $allVariants[] = $variant;
                    }
                }
            }

            // Get link header for pagination
            $linkHeader = $response->header('Link');
            $nextPageUrl = $this->getNextPageUrl($linkHeader);

            // Avoid rate limits
            if ($nextPageUrl) {
                usleep(500000); // 500ms delay
            }
        } while ($nextPageUrl);

        return $allVariants;
    }

    /**
     * Save a batch of inventory items
     */
    private function saveInventoryBatch($items)
    {
        foreach ($items as $item) {
            // Check if item exists
            $existingItem = DB::table('shopify_inventory_items')
                ->where('inventory_item_id', $item['id'])
                ->first();

            $itemDetails = [
                'inventory_item_id' => $item['id'],
                'sku' => $item['sku'] ?? null,
                'cost' => $item['cost'] ?? 0,
                'tracked' => $item['tracked'] ?? false,
                'requires_shipping' => $item['requires_shipping'] ?? true,
                'variant_id' => $item['variant_id'] ?? null,
                'raw_data' => json_encode($item),
                'synced_at' => now(),
            ];

            if ($existingItem) {
                // Update existing item
                DB::table('shopify_inventory_items')
                    ->where('inventory_item_id', $item['id'])
                    ->update($itemDetails);
            } else {
                // Insert new item
                DB::table('shopify_inventory_items')->insert($itemDetails);
            }
        }
    }

    /**
     * Get inventory levels for all locations
     */
    private function syncInventoryLevels()
    {
        Log::info('syncInventoryLevels');
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->accessToken
        ])->get("{$this->baseUrl}/locations.json");

        if (!$response->successful()) {
            Log::error("Failed to fetch locations: " . $response->body());
            return false;
        }

        $locations = $response->json()['locations'] ?? [];
        Log::info("Fetched locations: " . count($locations));

        foreach ($locations as $location) {

            $locationId = (string)$location['id'];

            if (in_array($locationId, $this->ignoredLocationIds)) {
                Log::info("Skipping ignored location: $locationId");
                continue;
            }

            $locationId = $location['id'];
            $nextPageUrl = "{$this->baseUrl}/inventory_levels.json?location_id={$locationId}&limit=250";

            do {
                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => $this->accessToken
                ])->get($nextPageUrl);

                if (!$response->successful()) {
                    Log::error("Failed to fetch inventory levels for location {$locationId}: " . $response->body());
                    continue;
                }

                $data = $response->json();
                $levels = $data['inventory_levels'] ?? [];
                Log::info("Processing " . count($levels) . " levels for location {$locationId}");

                foreach ($levels as $level) {
                    $updatedAt = isset($level['updated_at'])
                        ? Carbon::parse($level['updated_at'])->toDateTimeString()
                        : now();

                    DB::table('shopify_inventory_levels')->updateOrInsert(
                        [
                            'inventory_item_id' => (string)$level['inventory_item_id'],
                            'location_id' => (string)$level['location_id'],
                        ],
                        [
                            'available' => $level['available'] ?? 0,
                            'updated_at' => $updatedAt,
                            'synced_at' => now(),
                        ]
                    );
                }

                $linkHeader = $response->header('Link');
                $nextPageUrl = $this->getNextPageUrl($linkHeader);

                if ($nextPageUrl) {
                    usleep(500000);
                }
            } while ($nextPageUrl);
        }

        return true;
    }
}
