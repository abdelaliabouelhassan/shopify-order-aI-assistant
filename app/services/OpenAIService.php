<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use App\Models\ShopifyOrder;
use App\Models\ShopifyOrderItem;
use Exception;
use Carbon\Carbon;

class OpenAIService
{
    public function askAboutOrders(string $question): string
    {
        try {
            $classification = $this->classifyQuestion($question);

            if (!($classification['needs_data'] ?? true)) {
                return $this->answerGeneralQuestion($question);
            }

            return $this->handleDataQuestion($question);
        } catch (Exception $e) {
            return $this->fallbackResponse($question, $e->getMessage());
        }
    }

    private function classifyQuestion(string $question): array
    {
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "Determine if this question requires database access. Respond ONLY with JSON: {needs_data: bool, type: 'general|data'}"
                ],
                [
                    'role' => 'user',
                    'content' => "Question examples needing data:\n"
                        . "- What's the total revenue last month?\n"
                        . "- How many orders are pending?\n"
                        . "- Which products sold best in March?\n"
                        . "- What's our current inventory level for SKU ABC123?\n"
                        . "- What's the profit margin on our top products?\n\n"
                        . "General questions:\n"
                        . "- Hello\n"
                        . "- How does this work?\n"
                        . "- Explain shipping policies\n\n"
                        . "Question to classify: $question"
                ]
            ],
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => 100,
        ]);

        return json_decode($response->choices[0]->message->content, true);
    }

    private function answerGeneralQuestion(string $question): string
    {
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "You're an e-commerce assistant for our Shopify store. "
                        . "Be friendly and helpful. For data questions, say you can analyze orders, inventory, and product performance. "
                        . "Don't mention technical details about databases."
                ],
                ['role' => 'user', 'content' => $question]
            ],
            'max_tokens' => 500
        ]);

        return $response->choices[0]->message->content;
    }

    private function handleDataQuestion(string $question): string
    {
        $query = $this->generateSqlQuery($question);
        $results = DB::select($query);
        return $this->interpretResults($results, $question, $query);
    }

    private function generateSqlQuery(string $question): string
    {
        // Prepare current time references
        $currentDate = Carbon::now()->format('Y-m-d');
        $currentYear = Carbon::now()->year;
        $currentMonth = Carbon::now()->month;
        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d');
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d');
        $currentQuarterStart = Carbon::now()->startOfQuarter()->format('Y-m-d');
        $currentQuarterEnd = Carbon::now()->endOfQuarter()->format('Y-m-d');
        $lastQuarterStart = Carbon::now()->subMonths(3)->startOfQuarter()->format('Y-m-d');
        $lastQuarterEnd = Carbon::now()->subMonths(3)->endOfQuarter()->format('Y-m-d');
        $yearToDateStart = Carbon::now()->startOfYear()->format('Y-m-d');

        $schema = "
        Tables:
        - shopify_orders (id, shopify_id, order_number, email, created_at, updated_at, financial_status, fulfillment_status, total_price, total_tax, currency, tags, province, province_code, city, zip, address1  customer_data, shipping_address, billing_address)
        - shopify_order_items (id, order_id, shopify_line_item_id, product_id, variant_id, vendor, title, quantity, price, sku)
        - shopify_inventory_items (id, inventory_item_id, sku, cost, tracked, requires_shipping, variant_id)
        - shopify_inventory_levels (id, inventory_item_id, available, updated_at)
        
        Relationships:
        - shopify_order_items.order_id = shopify_orders.id
        - shopify_order_items.variant_id = shopify_inventory_items.variant_id
        - shopify_inventory_levels.inventory_item_id = shopify_inventory_items.inventory_item_id
        - shopify_order_items.sku = shopify_inventory_items.sku
        ";

        $timeContext = "
        Current date: $currentDate
        Current year: $currentYear
        Current month: $currentMonth
        Last month date range: $lastMonthStart to $lastMonthEnd
        Current quarter date range: $currentQuarterStart to $currentQuarterEnd
        Last quarter date range: $lastQuarterStart to $lastQuarterEnd
        Year to date: $yearToDateStart to $currentDate
        ";

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "Generate SQL queries for e-commerce data. Follow these rules:\n"
                        . "1. ONLY use SELECT statements\n"
                        . "2. Use only shopify_orders, shopify_order_items, shopify_inventory_items and shopify_inventory_levels tables\n"
                        . "3. Use the provided current date information for time-based queries - DO NOT use hardcoded dates\n"
                        . "4. For profit calculations, use (order_items.price - inventory_items.cost) as the profit per unit\n"
                        . "5. JSON fields (customer_data, shipping_address, billing_address) can be accessed using JSON_EXTRACT\n"
                        . "6. When a query refers to 'last month', 'current quarter', 'last quarter', etc., use the date ranges provided\n"
                        . "7. When a query mentions 'current inventory', refer to the latest data in shopify_inventory_levels\n"
                        . "8. Return JSON with 'query' and 'reasoning' fields"
                ],
                [
                    'role' => 'user',
                    'content' => "Schema: $schema\nTime Context: $timeContext\nQuestion: $question"
                ]
            ],
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => 600,
        ]);

        $result = json_decode($response->choices[0]->message->content);
        Log::info($result->query);
        return $this->validateQuery($result->query);
    }

    private function validateQuery(string $query): string
    {
        // Block forbidden operations
        if (preg_match('/\b(INSERT|UPDATE|DELETE|DROP|ALTER|EXEC|TRUNCATE|CREATE|GRANT|REVOKE)\b/i', $query)) {
            throw new Exception("Query contains forbidden operations");
        }

        // Allow only specified tables
        $allowedTables = [
            'shopify_orders',
            'shopify_order_items',
            'shopify_inventory_items',
            'shopify_inventory_levels'
        ];

        $tablePattern = implode('|', array_map(function ($table) {
            return preg_quote($table, '/');
        }, $allowedTables));

        if (!preg_match_all('/\b(' . $tablePattern . ')\b/i', $query)) {
            throw new Exception("Query accesses unauthorized tables");
        }

        // Enforce SELECT statements only
        if (!preg_match('/^SELECT/i', $query)) {
            throw new Exception("Only SELECT queries are allowed");
        }

        return $query;
    }

    private function interpretResults(array $results, string $question, string $query): string
    {
        $data = json_encode($results, JSON_PRETTY_PRINT);
        $isEmpty = empty($results);
        Log::info('data', ['data' => $data]);

        // Provide time context to the AI
        $currentDate = Carbon::now()->format('Y-m-d');
        $lastMonth = Carbon::now()->subMonth()->format('F Y');
        $currentQuarter = 'Q' . ceil(Carbon::now()->month / 3) . ' ' . Carbon::now()->year;
        $lastQuarter = 'Q' . ceil(Carbon::now()->subMonths(3)->month / 3) . ' ' . Carbon::now()->subMonths(3)->year;

        return OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "Analyze this e-commerce data and answer the question. "
                        . "Be concise but thorough. Mention numbers where relevant. "
                        . "For profit calculations, remember profit = revenue - cost. "
                        . "For inventory questions, consider current available quantities. "
                        . "Current date: $currentDate. "
                        . "Last month refers to: $lastMonth. "
                        . "Current quarter refers to: $currentQuarter. "
                        . "Last quarter refers to: $lastQuarter. "
                        . ($isEmpty ? "IMPORTANT: The query returned no results. This doesn't mean data is missing, incomplete, or that there's an error. "
                            . "It could simply mean no records match the specified criteria. "
                            . "For example, if asked about orders with a specific status and none exist with that status, "
                            . "the correct answer is that there are zero such orders. "
                            . "Your response should be specific and informative about what the empty result set actually means in the context of the question. "
                            . "Avoid saying 'data is missing or incomplete' - instead, explain what the empty result means about the business situation. "
                            . "Example: 'Based on the data, there were no sales in the specified category during the time period.' "
                            : "")
                ],
                [
                    'role' => 'user',
                    'content' => "Question: $question\nSQL Query: $query\nData:\n$data\nAnswer:"
                ]
            ],
            'max_tokens' => 1000
        ])->choices[0]->message->content;
    }

    private function fallbackResponse(string $question, string $error): string
    {
        return OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "Apologize that the data analysis failed. "
                        . "Provide helpful general answer. Error: $error"
                ],
                ['role' => 'user', 'content' => $question]
            ]
        ])->choices[0]->message->content;
    }
}
