<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class ShopifyOrderAnalyst
{
    protected $assistantId;
    protected $fileId;
    protected $threadId;
    protected $timeout = 90000; // Increased timeout in seconds
    protected $apiKey;
    protected $baseUrl = 'https://api.openai.com/v1';
    protected $cachePrefix = 'shopify_analyst_';

    /**
     * Initialize the service with the existing CSV file
     *
     * @param string $csvPath Path to the CSV file (defaults to the public file)
     * @param int $timeout Request timeout in seconds
     * @return self
     */
    public function __construct(string $csvPath = null, int $timeout = 120)
    {
        // Use the provided path or default to your public file
        $csvPath = $csvPath ?? public_path('index.php');
        $this->timeout = $timeout;
        $this->apiKey = config('openai.api_key');

        if (empty($this->apiKey)) {
            throw new Exception("OpenAI API key not configured");
        }

        // Try to restore from cache if exists
        $this->restoreFromCache();

        // Only proceed with setup if not restored from cache
        if (empty($this->assistantId) || empty($this->fileId)) {
            $this->setupAnalyst($csvPath);
        }
    }

    /**
     * Set up the analyst by uploading file and creating assistant
     */
    protected function setupAnalyst(string $csvPath)
    {
        try {
            if (!file_exists($csvPath)) {
                throw new Exception("CSV file not found at: {$csvPath}");
            }

            // Try to use a more reliable method for file upload
            $this->fileId = $this->uploadFileWithRetry($csvPath);
            // $this->fileId = 'asst_YkS7Y6DECaL14KQsoJM072DG';
            Log::info('file id is ', [$this->fileId]);
            if (empty($this->fileId)) {
                throw new Exception("File upload failed after multiple attempts");
            }

            $this->createAssistant();

            // Save to cache
            $this->saveToCache();
        } catch (Exception $e) {
            Log::error('ShopifyOrderAnalyst initialization error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Upload file with retry logic and different methods
     */
    protected function uploadFileWithRetry(string $filePath, $maxAttempts = 3)
    {
        $attempts = 0;
        $methods = ['curl', 'http', 'curl_chunked'];

        while ($attempts < $maxAttempts) {
            $method = $methods[$attempts % count($methods)];
            Log::info("Attempting file upload with method: {$method} (attempt " . ($attempts + 1) . ")");

            try {
                switch ($method) {
                    case 'curl':
                        return $this->uploadWithCurl($filePath);
                    case 'http':
                        return $this->uploadWithHttp($filePath);
                    case 'curl_chunked':
                        return $this->uploadWithChunkedCurl($filePath);
                }
            } catch (Exception $e) {
                Log::warning("Upload attempt  failed with method {$method}: " . $e->getMessage());
            }

            $attempts++;
            // Add a small delay between attempts
            sleep(2);
        }

        return null;
    }

    /**
     * Upload with standard HTTP client
     */
    protected function uploadWithHttp(string $filePath)
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
        ])
            ->timeout($this->timeout)
            ->attach(
                'file',
                file_get_contents($filePath),
                basename($filePath)
            )
            ->post("{$this->baseUrl}/files", [
                'purpose' => 'assistants'
            ]);

        if ($response->failed()) {
            throw new Exception("HTTP upload failed: " . $response->body());
        }

        return $response->json('id');
    }

    /**
     * Upload with standard CURL
     */
    protected function uploadWithCurl(string $filePath)
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => "{$this->baseUrl}/files",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$this->apiKey}",
            ],
        ]);

        $cfile = new \CURLFile($filePath, 'text/csv', basename($filePath));
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'purpose' => 'assistants',
            'file' => $cfile,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error || $httpCode !== 200) {
            throw new Exception("cURL upload error: {$error}, HTTP status: {$httpCode}, Response: {$response}");
        }

        $data = json_decode($response, true);
        return $data['id'] ?? null;
    }

    /**
     * Upload with chunked CURL (for large files or when facing gateway errors)
     */
    protected function uploadWithChunkedCurl(string $filePath)
    {
        // Use the OpenAI beta API endpoint for tus protocol uploads
        $tusUrl = "https://upload.openai.com/v1/files";

        $fileSize = filesize($filePath);
        $fileName = basename($filePath);

        // Create upload session
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $tusUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$this->apiKey}",
                "Content-Type: application/json",
            ],
            CURLOPT_POSTFIELDS => json_encode([
                "purpose" => "assistants",
                "filename" => $fileName,
            ]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode !== 200) {
            curl_close($ch);
            throw new Exception("Failed to create upload session: HTTP {$httpCode}, Response: {$response}");
        }

        $data = json_decode($response, true);
        $uploadUrl = $data['upload_url'] ?? null;
        $fileId = $data['id'] ?? null;

        curl_close($ch);

        if (empty($uploadUrl) || empty($fileId)) {
            throw new Exception("Invalid upload session response: " . $response);
        }

        // Upload file to the provided URL
        $file = fopen($filePath, 'r');
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $uploadUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "PUT",
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$this->apiKey}",
                "Content-Type: application/octet-stream",
                "Content-Length: {$fileSize}",
            ],
            CURLOPT_INFILE => $file,
            CURLOPT_INFILESIZE => $fileSize,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        fclose($file);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception("Failed to upload file: HTTP {$httpCode}, Response: {$response}");
        }

        return $fileId;
    }

    /**
     * Create an AI assistant with the uploaded file
     */
    protected function createAssistant()
    {
        if (empty($this->fileId)) {
            throw new Exception("No file uploaded. File upload failed.");
        }

        try {
            // First create a vector store with the uploaded file
            $vectorStoreId = $this->createVectorStoreWithFile();

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v2'
            ])->post("{$this->baseUrl}/assistants", [
                'name' => 'Shopify Order Analyst',
                'instructions' => 'You are php expert answer any questions the users gived you with provided php file',
                'model' => 'gpt-4o',
                'tools' => [
                    ['type' => 'code_interpreter'],
                    ['type' => 'file_search']
                ],
                'tool_resources' => [
                    'file_search' => [
                        'vector_store_ids' => [$vectorStoreId]
                    ]
                ]
            ]);

            if ($response->failed()) {
                throw new Exception("Assistant creation failed: " . $response->body());
            }

            $this->assistantId = $response->json('id');
            Log::info("Assistant created successfully with ID: {$this->assistantId}");
        } catch (Exception $e) {
            Log::error("Error creating assistant: " . $e->getMessage());
            throw new Exception("Failed to create assistant: " . $e->getMessage());
        }
    }

    protected function createVectorStoreWithFile()
    {
        // Create vector store
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
            'OpenAI-Beta' => 'assistants=v2'
        ])->post("{$this->baseUrl}/vector_stores", [
            'name' => 'Shopify Orders Vector Store',
            'file_ids' => [$this->fileId]
        ]);

        if ($response->failed()) {
            throw new Exception("Vector store creation failed: " . $response->body());
        }

        return $response->json('id');
    }

    /**
     * Ask a question about the Shopify orders
     *
     * @param string $question The question to ask
     * @return string The AI response
     */
    public function ask(string $question)
    {
        if (empty($this->assistantId)) {
            throw new Exception("Assistant not created yet.");
        }

        try {
            // Create a new thread or use existing one
            if (empty($this->threadId)) {
                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                    'OpenAI-Beta' => 'assistants=v2',
                ])
                    ->post("{$this->baseUrl}/threads");

                if ($response->failed()) {
                    throw new Exception("Failed to create thread: " . $response->body());
                }

                $this->threadId = $response->json('id');
                $this->saveToCache();
            }

            // Add the message to the thread
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v2',
            ])
                ->post("{$this->baseUrl}/threads/{$this->threadId}/messages", [
                    'role' => 'user',
                    'content' => $question
                ]);

            if ($response->failed()) {
                throw new Exception("Failed to create message: " . $response->body());
            }

            // Run the assistant
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v2',
            ])
                ->post("{$this->baseUrl}/threads/{$this->threadId}/runs", [
                    'assistant_id' => $this->assistantId,
                    'instructions' => 'Format response with markdown tables or charts when applicable. Be concise but thorough.'
                ]);

            if ($response->failed()) {
                throw new Exception("Failed to create run: " . $response->body());
            }

            $runId = $response->json('id');
            return $this->waitForResponse($runId);
        } catch (Exception $e) {
            Log::error("Error in ask method: " . $e->getMessage());
            throw new Exception("Failed to process question: " . $e->getMessage());
        }
    }

    /**
     * Wait for the AI response to be ready
     */
    protected function waitForResponse(string $runId, int $maxRetries = 30, int $delay = 3)
    {
        $retries = 0;

        do {
            if ($retries >= $maxRetries) {
                throw new Exception("Timeout waiting for analysis completion");
            }

            sleep($delay);
            $retries++;

            try {
                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'OpenAI-Beta' => 'assistants=v2',
                ])
                    ->get("{$this->baseUrl}/threads/{$this->threadId}/runs/{$runId}");

                if ($response->failed()) {
                    throw new Exception("Failed to retrieve run status: " . $response->body());
                }

                $status = $response->json('status');

                // Handle failed status
                if ($status === 'failed') {
                    $errorMessage = $response->json('last_error.message') ?? 'Unknown error';
                    throw new Exception("Analysis failed: " . $errorMessage);
                }
            } catch (Exception $e) {
                Log::error("Error checking run status: " . $e->getMessage());
                throw new Exception("Failed to check run status: " . $e->getMessage());
            }
        } while (in_array($status, ['in_progress', 'queued']));

        return $this->getLatestResponse();
    }

    /**
     * Get the latest response from the thread
     */
    protected function getLatestResponse()
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'OpenAI-Beta' => 'assistants=v2',
            ])
                ->get("{$this->baseUrl}/threads/{$this->threadId}/messages", [
                    'order' => 'desc',
                    'limit' => 1
                ]);

            if ($response->failed()) {
                throw new Exception("Failed to retrieve messages: " . $response->body());
            }

            $data = $response->json();

            if (empty($data['data'])) {
                return "No response received";
            }

            $responseContent = '';
            foreach ($data['data'][0]['content'] as $content) {
                if ($content['type'] === 'text') {
                    $responseContent .= $content['text']['value'] . "\n";
                }
            }

            return $responseContent;
        } catch (Exception $e) {
            Log::error("Error getting latest response: " . $e->getMessage());
            throw new Exception("Failed to get response: " . $e->getMessage());
        }
    }

    /**
     * Save important IDs to cache
     */
    protected function saveToCache()
    {
        $cacheKey = $this->cachePrefix . md5($this->apiKey);
        $data = [
            'file_id' => $this->fileId,
            'assistant_id' => $this->assistantId,
            'thread_id' => $this->threadId,
            'expires' => now()->addDays(7)->timestamp,
        ];

        Cache::put($cacheKey, $data, now()->addDays(7));
    }

    /**
     * Restore from cache if available
     */
    protected function restoreFromCache()
    {
        $cacheKey = $this->cachePrefix . md5($this->apiKey);
        $data = Cache::get($cacheKey);

        if (!empty($data) && $data['expires'] > time()) {
            $this->fileId = $data['file_id'] ?? null;
            $this->assistantId = $data['assistant_id'] ?? null;
            $this->threadId = $data['thread_id'] ?? null;

            Log::info("Restored analyst session from cache");
            return true;
        }

        return false;
    }

    /**
     * Clean up resources created by this service
     */
    public function cleanup()
    {
        try {
            if (!empty($this->fileId)) {
                Http::withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'OpenAI-Beta' => 'assistants=v2',
                ])
                    ->delete("{$this->baseUrl}/files/{$this->fileId}");
            }

            if (!empty($this->assistantId)) {
                Http::withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'OpenAI-Beta' => 'assistants=v2',
                ])
                    ->delete("{$this->baseUrl}/assistants/{$this->assistantId}");
            }

            $this->fileId = null;
            $this->assistantId = null;
            $this->threadId = null;

            $cacheKey = $this->cachePrefix . md5($this->apiKey);
            Cache::forget($cacheKey);

            Log::info("ShopifyOrderAnalyst resources cleaned up");
        } catch (Exception $e) {
            Log::error("Error during cleanup: " . $e->getMessage());
        }
    }
}
