<?php

use App\Http\Controllers\AskController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Route d'accueil avec informations d'environnement pour la page Welcome
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Groupe de routes protégées : triple sécurité Jetstream (auth + session + email vérifié)
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    // Point d'entrée principal : création automatique de conversation + cleanup des conversations vides
    Route::get('/ask', [ConversationController::class, 'ask'])->name('ask.index');

    // CRUD complet des conversations avec autorisation implicite via Model Binding
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::post('/conversations', [ConversationController::class, 'store'])->name('conversations.store');
    Route::get('/conversations/{conversation}/messages', [ConversationController::class, 'messages'])->name('conversations.messages');
    Route::patch('/conversations/{conversation}/model', [ConversationController::class, 'updateModel'])->name('conversations.updateModel');
    Route::delete('/conversations/{conversation}', [ConversationController::class, 'destroy'])->name('conversations.destroy');

    // Système double de messaging : classique (rechargement) vs streaming (SSE)
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])->name('messages.store');
    Route::post('/conversations/{conversation}/stream', [MessageController::class, 'sendMessageStream'])
        ->name('conversations.stream');

    // API pour instructions personnalisées avec double middleware auth (redondant mais sécurisé)
    Route::get('/profile/custom-instructions', [ProfileController::class, 'getCustomInstructions'])
        ->name('profile.get-custom-instructions')
        ->middleware('auth');
    Route::post('/profile/custom-instructions', [ProfileController::class, 'updateCustomInstructions'])
        ->name('profile.custom-instructions')
        ->middleware('auth');
});
