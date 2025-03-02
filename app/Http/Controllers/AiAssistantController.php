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
}
