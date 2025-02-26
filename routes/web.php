<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Services\ShopifyService;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return Inertia::render('Welcome', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'laravelVersion' => Application::VERSION,
            'phpVersion' => PHP_VERSION,
        ]);
    });

    Route::get('/conversations', [ChatController::class, 'getConversations']);
    Route::get('/conversations/{id}', [ChatController::class, 'getConversation']);
    Route::post('/conversations', [ChatController::class, 'createConversation']);
    Route::put('/conversations/{id}', [ChatController::class, 'updateConversation']);
    Route::delete('/conversations/{id}', [ChatController::class, 'deleteConversation']);
    Route::post('/conversations/{id}/messages', [ChatController::class, 'sendMessage']);
    Route::delete('/conversations/{id}/messages', [ChatController::class, 'clearConversation']);
});


Route::get('/testing', function () {
    return $orders = (new ShopifyService())->getInventory();
});

Route::get('/orders', [OrderController::class, 'index']);
Route::post('/ask', [OrderController::class, 'ask'])->name('ask');



Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
