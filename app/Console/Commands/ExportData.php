<?php

namespace App\Console\Commands;

use App\Jobs\ExportData as JobsExportData;
use Illuminate\Console\Command;

class ExportData extends Command
{
    protected $signature = 'shopify:export';
    protected $description = 'export data from  database';

    public function handle()
    {


        $this->info("Dispatching export data");

        // Dispatch the job to the queue
        JobsExportData::dispatch();

        $this->info("Job exportdata dispatched  successfully");

        return 0;
    }
}
