<?php

namespace App\Jobs;

use App\Services\ShopifyOrderAnalyst;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class updateAiAssistant implements ShouldQueue
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
        $filePath1 = public_path('shopify_orders.csv');
        $filePath2 = public_path('shopify_products.csv');


        // Get existing file_id and assistant_id from database
        $assistantData = DB::table('ai_assistants')

            ->where('type', 'shopify_analyst')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($assistantData) {
            $analyst = new ShopifyOrderAnalyst(
                $assistantData->file_id,
                $assistantData->assistant_id
            );

            // Update with new knowledge file
            $analyst->updateKnowledge([$filePath1, $filePath2]);
        }

        // Initialize with existing IDs


        // return response()->json([
        //     'success' => true,
        //     'file_id' => $analyst->getFileId(),
        //     'assistant_id' => $analyst->getAssistantId()
        // ]);
    }
}
