<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Services\ShopifyOrderAnalyst;
use App\Services\ShopifyService;
use App\Services\ShopifyToOpenAIService;
use Illuminate\Foundation\Application;
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


Route::get('/testing', function () {
    $analyst = new ShopifyOrderAnalyst();
    $response =  $analyst->ask("how many 'use' you it been used in the php file we gived you ? ");
    return $response;
});


require __DIR__ . '/auth.php';
