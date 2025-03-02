<?php

namespace App\Jobs;

use App\Services\ShopifyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncShopifyData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $syncType;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($syncType = 'all')
    {
        $this->syncType = $syncType;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(ShopifyService $shopifyService)
    {
        Log::info("Starting Shopify sync in queue: {$this->syncType}");

        switch ($this->syncType) {
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
                $this->syncInventory($shopifyService);
                break;
            default:
                Log::error("Unknown sync type: {$this->syncType}");
                return;
        }
    }

    private function syncAll(ShopifyService $shopifyService)
    {
        $this->syncOrders($shopifyService);
        $this->syncInventory($shopifyService);
    }

    private function syncOrders(ShopifyService $shopifyService)
    {
        Log::info('Syncing all orders...');
        $result = $shopifyService->getAllOrders();
        $this->outputResult('Orders', $result);
    }

    private function syncInventory(ShopifyService $shopifyService)
    {
        Log::info('Syncing all inventory...');
        $result = $shopifyService->getAllInventory();
        $this->outputResult('Inventory', $result);
    }


    private function syncRecentOrders(ShopifyService $shopifyService)
    {
        Log::info('Syncing recent orders...');
        $result = $shopifyService->syncRecentOrders(1); // Last 1 days
        $this->outputResult('Recent orders', $result);
    }

    private function outputResult($type, $result)
    {
        if ($result['success']) {
            Log::info("{$type} sync completed: {$result['message']}");
        } else {
            Log::error("{$type} sync failed: {$result['message']}");
        }
    }
}
