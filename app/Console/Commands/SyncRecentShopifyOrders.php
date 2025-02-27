<?php

namespace App\Console\Commands;

use App\Services\ShopifyService;
use Illuminate\Console\Command;

class SyncRecentShopifyOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */


    protected $signature = 'shopify:sync-recent {days=7}';
    protected $description = 'Sync recent orders from Shopify';

    /**
     * Execute the console command.
     */
    public function handle(ShopifyService $shopifyService)
    {
        $days = $this->argument('days');
        $this->info("Syncing orders from the last $days days...");

        $result = $shopifyService->syncRecentOrders($days);

        if ($result['success']) {
            $this->info("Synced {$result['count']} recent orders successfully");
            return Command::SUCCESS;
        } else {
            $this->error($result['message']);
            return Command::FAILURE;
        }
    }
}
