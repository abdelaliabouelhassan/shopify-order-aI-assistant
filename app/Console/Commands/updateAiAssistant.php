<?php

namespace App\Console\Commands;

use App\Jobs\updateAiAssistant as JobsUpdateAiAssistant;
use Illuminate\Console\Command;

class updateAiAssistant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:update-ai';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Dispatching update ai job:");

        // Dispatch the job to the queue
        JobsUpdateAiAssistant::dispatch();

        $this->info("Job update ai dispatched successfully");

        return 0;
    }
}
