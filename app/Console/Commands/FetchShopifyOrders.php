<?php

namespace App\Console\Commands;

use App\Jobs\SyncShopifyData;
use Illuminate\Console\Command;

class FetchShopifyOrders extends Command
{
    protected $signature = 'shopify:sync {type=all : The type of sync to run (all, orders, inventory, recent)}';
    protected $description = 'Sync data from Shopify to local database';

    public function handle()
    {
        $type = $this->argument('type');

        $this->info("Dispatching Shopify sync job: {$type}");

        // Dispatch the job to the queue
        SyncShopifyData::dispatch($type);

        $this->info("Job dispatched successfully");

        return 0;
    }
}
