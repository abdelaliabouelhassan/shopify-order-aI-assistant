<?php


namespace App\Services;

use App\Models\Order;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAIService
{
    public function askAboutAllOrders($question)
    {
        // Get all orders with their data
        $orders = Order::all();

        // Prepare order context
        $orderContext = "Store Orders Data:\n";
        foreach ($orders as $order) {
            $orderData = json_decode($order->order_data, true);
            $orderContext .= sprintf(
                "Order ID: %s\nCustomer: %s\nItems: %s\nTotal: %s\n\n",
                $orderData['id'],
                $orderData['customer']['name'] ?? 'Unknown',
                implode(', ', array_column($orderData['line_items'], 'name')),
                $orderData['total_price']
            );
        }

        // Create the prompt
        $prompt = "You are an e-commerce assistant analyzing Shopify orders. Use this order data:\n\n"
            . $orderContext
            . "\nQuestion: " . $question
            . "\nAnswer:";

        // Call OpenAI API
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a helpful e-commerce assistant that analyzes order data.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 1000
        ]);

        return $response->choices[0]->message->content;
    }
}
