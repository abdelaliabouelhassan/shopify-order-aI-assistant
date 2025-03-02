<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return Inertia::render('Welcome', [
            'title' => 'AI ASSISTANCE',
        ]);
    })->name('home');

    Route::get('/conversations', [ChatController::class, 'getConversations']);
    Route::get('/conversations/{id}', [ChatController::class, 'getConversation']);
    Route::post('/conversations', [ChatController::class, 'createConversation']);
    Route::put('/conversations/{id}', [ChatController::class, 'updateConversation']);
    Route::delete('/conversations/{id}', [ChatController::class, 'deleteConversation']);
    Route::post('/conversations/{id}/messages', [ChatController::class, 'sendMessage']);
    Route::delete('/conversations/{id}/messages', [ChatController::class, 'clearConversation']);
});


// Route::get('/setup-ai', [AiAssistantController::class, 'setupAssistant']);
// Route::get('/update-ai', [AiAssistantController::class, 'updateKnowledge']);
// Route::get('/ask', [AiAssistantController::class, 'askQuestion']);




require __DIR__ . '/auth.php';
