<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Exception;

class ShopifyOrderAnalyst
{
    protected $assistantId;
    protected $fileId;
    protected $threadId;
    protected $timeout = 90000;
    protected $apiKey;
    protected $baseUrl = 'https://api.openai.com/v1';
    protected $cachePrefix = 'shopify_analyst_';

    /**
     * Initialize the service with optional parameters
     *
     * 
     * @param string|null $fileId Existing file ID from database
     * @param string|null $assistantId Existing assistant ID from database
     * @param int $timeout Request timeout in seconds
     * @return self
     */
    public function __construct(string $fileId = null, string $assistantId = null, int $timeout = 120)
    {
        $this->timeout = $timeout;
        $this->apiKey = config('openai.api_key');

        if (empty($this->apiKey)) {
            throw new Exception("OpenAI API key not configured");
        }

        // If file ID and assistant ID are provided, use them
        if ($fileId && $assistantId) {
            $this->fileId = $fileId;
            $this->assistantId = $assistantId;
            Log::info("Using provided file ID and assistant ID");
        } else {
            $this->loadFromDatabase();

            // If still not loaded, try cache
            if (empty($this->fileId) || empty($this->assistantId)) {
                $this->restoreFromCache();
            }
        }

        // Create a thread if none exists
        $this->createThreadIfNeeded();
    }

    /**
     * Load file ID and assistant ID from database
     */
    protected function loadFromDatabase()
    {
        try {
            $record = DB::table('ai_assistants')
                ->where('type', 'shopify_analyst')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($record) {
                $this->fileId = $record->file_id;
                $this->assistantId = $record->assistant_id;
                Log::info("Loaded analyst data from database ");
                return true;
            }
        } catch (Exception $e) {
            Log::error("Error loading from database: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Save file ID and assistant ID to database
     * Updates existing record if one exists, otherwise creates a new one
     */
    protected function saveToDatabase()
    {
        if (empty($this->fileId) || empty($this->assistantId)) {
            return false;
        }

        try {
            // Check if a record exists
            $existingRecord = DB::table('ai_assistants')
                ->where('type', 'shopify_analyst')
                ->first();

            if ($existingRecord) {
                // Update the existing record
                DB::table('ai_assistants')
                    ->where('id', $existingRecord->id)
                    ->update([
                        'file_id' => $this->fileId,
                        'assistant_id' => $this->assistantId,
                        'updated_at' => now(),
                    ]);
                Log::info("Updated existing analyst data in database");
            } else {
                // Insert a new record if none exists
                DB::table('ai_assistants')->insert([
                    'file_id' => $this->fileId,
                    'assistant_id' => $this->assistantId,
                    'type' => 'shopify_analyst',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                Log::info("Saved new analyst data to database");
            }

            return true;
        } catch (Exception $e) {
            Log::error("Error saving to database: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Upload new files (orders.csv and products.csv) and update the assistant
     *
     * @param array $filePaths Array of paths to CSV files
     * @param bool $createNewAssistant Whether to create a new assistant or update existing
     * @return bool Success status
     */
    public function updateKnowledge(array $filePaths, bool $createNewAssistant = false)
    {
        foreach ($filePaths as $filePath) {
            if (!file_exists($filePath)) {
                throw new Exception("File not found at: {$filePath}");
            }
        }

        try {
            // Upload all files
            $fileIds = [];
            foreach ($filePaths as $filePath) {
                $fileId = $this->uploadFileWithRetry($filePath);
                if (empty($fileId)) {
                    throw new Exception("File upload failed for {$filePath} after multiple attempts");
                }
                $fileIds[] = $fileId;
            }

            // Keep track of the first file ID for reference
            $this->fileId = $fileIds[0];

            // If we want a new assistant or don't have an existing one
            if ($createNewAssistant || empty($this->assistantId)) {
                // Create a new assistant with these files
                $this->createAssistant($fileIds);
            } else {
                // Update the existing assistant with the new files
                $this->updateAssistantWithFiles($fileIds);
            }

            // Save the updated IDs
            $this->saveToCache();
            $this->saveToDatabase();

            return true;
        } catch (Exception $e) {
            Log::error("Update knowledge error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update existing assistant with new files
     */
    protected function updateAssistantWithFiles(array $fileIds)
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
            'OpenAI-Beta' => 'assistants=v2'
        ])->put("{$this->baseUrl}/assistants/{$this->assistantId}", [
            'tools' => [
                ['type' => 'code_interpreter']
            ],
            'tool_resources' => [
                'code_interpreter' => [
                    'file_ids' => $fileIds
                ]
            ]
        ]);

        if ($response->failed()) {
            throw new Exception("Assistant update failed: " . $response->body());
        }

        Log::info("Assistant updated successfully with new files");
    }

    /**
     * Set up a new assistant with files
     *
     * @param array $filePaths Array of paths to CSV files
     */
    public function setupNewAssistant(array $filePaths)
    {
        try {
            foreach ($filePaths as $filePath) {
                if (!file_exists($filePath)) {
                    throw new Exception("File not found at: {$filePath}");
                }
            }

            // Upload all files
            $fileIds = [];
            foreach ($filePaths as $filePath) {
                $fileId = $this->uploadFileWithRetry($filePath);
                if (empty($fileId)) {
                    throw new Exception("File upload failed for {$filePath} after multiple attempts");
                }
                $fileIds[] = $fileId;
            }

            // Keep the first file ID for reference
            $this->fileId = $fileIds[0];

            // Create the assistant with all files
            $this->createAssistant($fileIds);

            // Create a thread
            $this->createThreadIfNeeded();

            // Save the IDs
            $this->saveToCache();
            $this->saveToDatabase();

            return true;
        } catch (Exception $e) {
            Log::error("Setup new assistant error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a thread if none exists
     */
    protected function createThreadIfNeeded()
    {
        if (empty($this->threadId)) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                    'OpenAI-Beta' => 'assistants=v2',
                ])->post("{$this->baseUrl}/threads");

                if ($response->failed()) {
                    throw new Exception("Failed to create thread: " . $response->body());
                }

                $this->threadId = $response->json('id');
                $this->saveToCache();
            } catch (Exception $e) {
                Log::error("Error creating thread: " . $e->getMessage());
                throw new Exception("Failed to create thread: " . $e->getMessage());
            }
        }
    }

    /**
     * Create an AI assistant with uploaded files
     * 
     * @param array $fileIds Array of file IDs to attach to the assistant
     */
    protected function createAssistant(array $fileIds = [])
    {
        if (empty($fileIds)) {
            throw new Exception("No files uploaded. File upload failed.");
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v2'
            ])->post("{$this->baseUrl}/assistants", [
                'name' => 'Shopify Order Analyst',
                'instructions' => "You are an expert in analyzing Shopify data. You analyze data present in CSV files using code interpreter. 
                Use pandas to read the CSV files. The files contain order data and product inventory data.
                Format your responses with markdown tables, charts, or bullet points.
                Always provide actionable insights. For example:
                - Highlight low-stock items and suggest restocking.
                - Identify trending products.
                - Flag delayed orders.
                When creating visualizations, use appropriate charts for the data.",
                'model' => 'gpt-4-turbo',
                'tools' => [
                    ['type' => 'code_interpreter']
                ],
                'tool_resources' => [
                    'code_interpreter' => [
                        'file_ids' => $fileIds
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
    /**
     * Upload file with retry logic and different methods
     */
    protected function uploadFileWithRetry(string $filePath, $maxAttempts = 6)
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
                Log::warning("Upload attempt failed with method {$method}: " . $e->getMessage());
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
            // Ensure thread exists
            $this->createThreadIfNeeded();

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
                    'instructions' => 'Use code interpreter to analyze the CSV files. Format response with markdown tables or charts when applicable.'
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
                } elseif ($content['type'] === 'image') {
                    // Handle image content if needed
                    $responseContent .= "[Image attachment available]\n";
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
     * Get current file ID
     */
    public function getFileId()
    {
        return $this->fileId;
    }

    /**
     * Get current assistant ID
     */
    public function getAssistantId()
    {
        return $this->assistantId;
    }

    /**
     * Get current thread ID
     */
    public function getThreadId()
    {
        return $this->threadId;
    }

    /**
     * Clean up resources created by this service
     * 
     * @param bool $removeFromDatabase Whether to remove from database
     */
    public function cleanup($removeFromDatabase = false)
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

            // Remove from database if requested
            if ($removeFromDatabase) {
                DB::table('ai_assistants')
                    ->where('file_id', $this->fileId)
                    ->where('assistant_id', $this->assistantId)
                    ->delete();
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
