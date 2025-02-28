<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\ShopifyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchShopifyOrders extends Command
{
    protected $signature = 'shopify:sync {type=all : The type of sync to run (all, orders, inventory, recent)}';
    protected $description = 'Sync data from Shopify to local database';

    public function handle(ShopifyService $shopifyService)
    {
        $type = $this->argument('type');

        $this->info("Starting Shopify sync: {$type}");
        Log::info("Starting Shopify sync: {$type}");

        switch ($type) {
            case 'all':
                $this->syncAll($shopifyService);
                break;
            case 'orders':
                $this->syncOrders($shopifyService);
                break;
            case 'inventory':
                $this->syncInventory($shopifyService);
                break;
            case 'recent':
                $this->syncRecentOrders($shopifyService);
                break;
            default:
                $this->error("Unknown sync type: {$type}");
                return 1;
        }

        return 0;
    }

    private function syncAll(ShopifyService $shopifyService)
    {
        $this->syncOrders($shopifyService);
        $this->syncInventory($shopifyService);
    }

    private function syncOrders(ShopifyService $shopifyService)
    {
        $this->info('Syncing all orders...');
        $result = $shopifyService->getAllOrders();
        $this->outputResult('Orders', $result);
    }

    private function syncInventory(ShopifyService $shopifyService)
    {
        $this->info('Syncing all inventory...');
        $result = $shopifyService->getAllInventory();
        $this->outputResult('Inventory', $result);
    }

    private function syncRecentOrders(ShopifyService $shopifyService)
    {
        $this->info('Syncing recent orders...');
        $result = $shopifyService->syncRecentOrders(2); // Last 7 days
        $this->outputResult('Recent orders', $result);
    }

    private function outputResult($type, $result)
    {
        if ($result['success']) {
            $this->info("{$type} sync completed: {$result['message']}");
            Log::info("{$type} sync completed: {$result['message']}");
        } else {
            $this->error("{$type} sync failed: {$result['message']}");
            Log::error("{$type} sync failed: {$result['message']}");
        }
    }
}
