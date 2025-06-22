<?php

use App\Http\Controllers\AskController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/ask', [ConversationController::class, 'ask'])->name('ask.index');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::post('/conversations', [ConversationController::class, 'store'])->name('conversations.store');
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])->name('messages.store');
    Route::get('/conversations/{conversation}/messages', [ConversationController::class, 'messages'])->name('conversations.messages');
    Route::patch('/conversations/{conversation}/model', [ConversationController::class, 'updateModel'])->name('conversations.updateModel');
    Route::delete('/conversations/{conversation}', [ConversationController::class, 'destroy'])->name('conversations.destroy');
    Route::post('/profile/custom-instructions', [ProfileController::class, 'updateCustomInstructions'])->name('profile.custom-instructions')->middleware('auth');
    Route::get('/profile/custom-instructions', [ProfileController::class, 'getCustomInstructions'])
        ->name('profile.get-custom-instructions')
        ->middleware('auth');
    Route::post('/conversations/{conversation}/stream', [MessageController::class, 'sendMessageStream'])
        ->name('conversations.stream');
});
