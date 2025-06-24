<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Schema;

/**
 * Contrôleur principal pour la gestion complète des conversations persistantes
 * Centralise la logique de CRUD et l'interface utilisateur via Inertia
 */
class ConversationController extends Controller
{
    /**
     * Redirection simple vers le point d'entrée principal
     */
    public function index()
    {
        return redirect()->route('ask.index');
    }

    /**
     * Point d'entrée principal : création automatique d'une nouvelle conversation
     * avec nettoyage préalable des conversations anciennes vides
     */
    public function ask()
    {
        $user = auth()->user();

        // Pattern de nettoyage automatique : supprime les conversations vides anciennes avant création
        Conversation::cleanupEmpty($user->id);

        // Création avec modèle personnalisé utilisateur ou fallback vers défaut
        $newConversation = Conversation::create([
            'user_id' => $user->id,
            'model' => $user->model ?? ChatService::DEFAULT_MODEL
        ]);

        // Récupération ordonnée par activité récente pour affichage chronologique
        $conversations = Conversation::where('user_id', $user->id)
            ->orderBy('updated_at', 'desc')
            ->get();

        $selectedConversation = $newConversation;
        $messages = collect(); // Collection vide pour nouvelle conversation

        $chatService = new ChatService();
        $models = $chatService->getModels();

        // Rendu Inertia avec payload complet pour interface SPA
        return Inertia::render('Ask/Index', [
            'conversations' => $conversations,
            'selectedConversation' => $selectedConversation->fresh(), // Refresh pour garantir l'état actuel
            'messages' => $messages,
            'models' => $models,
            'selectedModel' => $newConversation->model,
            // Sérialisation manuelle de l'utilisateur avec champs personnalisés pour Vue.js
            'auth' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name ?? '',
                    'email' => $user->email ?? '',
                    'custom_instructions' => $user->custom_instructions ?? '',
                    'custom_response_style' => $user->custom_response_style ?? '',
                    'enable_custom_instructions' => $user->enable_custom_instructions ?? true,
                    'custom_commands' => $user->custom_commands ?? '',
                ]
            ]
        ]);
    }

    /**
     * Création manuelle de conversation (utilisée par l'interface pour "Nouvelle conversation")
     */
    public function store()
    {
        $user = auth()->user();

        // Même pattern de nettoyage que ask()
        Conversation::cleanupEmpty(auth()->id());

        $conversation = Conversation::create([
            'user_id' => auth()->id(),
            'model' => auth()->user()->model ?? ChatService::DEFAULT_MODEL
        ]);

        $conversations = Conversation::where('user_id', auth()->id())
            ->orderBy('updated_at', 'desc')
            ->get();

        $chatService = new ChatService();
        $models = $chatService->getModels();

        return Inertia::render('Ask/Index', [
            'conversations' => $conversations,
            'selectedConversation' => $conversation,
            'messages' => collect(), // Collection vide pour nouvelle conversation
            'models' => $models,
            'selectedModel' => $conversation->model,
            // Pattern de sérialisation allégé avec only()
            'auth' => [
                'user' => $user->only(['id', 'name', 'email', 'custom_instructions', 'custom_response_style', 'enable_custom_instructions', 'custom_commands'])
            ]
        ]);
    }

    /**
     * Affichage d'une conversation existante avec ses messages
     */
    public function show(Conversation $conversation)
    {
        // Pattern de sécurité : vérification explicite de propriété
        if ($conversation->user_id !== auth()->id()) {
            abort(403);
        }

        $user = auth()->user();
        $conversations = Conversation::where('user_id', $user->id)
            ->orderBy('updated_at', 'desc')
            ->get();

        // Chargement ordonné des messages pour affichage chronologique
        $messages = $conversation->messages()->orderBy('created_at')->get();

        $chatService = new ChatService();
        $models = $chatService->getModels();

        return Inertia::render('Ask/Index', [
            'conversations' => $conversations,
            'selectedConversation' => $conversation,
            'messages' => $messages,
            'models' => $models,
            'selectedModel' => $conversation->model,
            'auth' => [
                'user' => $user->only(['id', 'name', 'email', 'custom_instructions', 'custom_response_style', 'enable_custom_instructions', 'custom_commands'])
            ]
        ]);
    }

    /**
     * Suppression avec logique de fallback automatique
     */
    public function destroy(Conversation $conversation)
    {
        // Pattern de sécurité identique
        if ($conversation->user_id !== auth()->id()) {
            abort(403);
        }

        $user = auth()->user();
        $conversationId = $conversation->id;
        $conversation->delete(); // Cascade delete automatique pour les messages

        $conversations = Conversation::where('user_id', auth()->id())
            ->orderBy('updated_at', 'desc')
            ->get();

        $selectedConversation = $conversations->first();

        // Logique de fallback : création automatique si plus de conversations
        if (!$selectedConversation) {
            Conversation::cleanupEmpty(auth()->id());
            $selectedConversation = Conversation::create([
                'user_id' => auth()->id(),
                'model' => auth()->user()->model ?? ChatService::DEFAULT_MODEL
            ]);
            $conversations = $conversations->push($selectedConversation);
        }

        $chatService = new ChatService();
        $models = $chatService->getModels();

        return Inertia::render('Ask/Index', [
            'conversations' => $conversations,
            'selectedConversation' => $selectedConversation,
            'messages' => $selectedConversation->messages()->orderBy('created_at')->get(),
            'models' => $models,
            'selectedModel' => $selectedConversation->model,
            'deletedConversationId' => $conversationId, // Information pour animation frontend
            'auth' => [
                'user' => $user->only(['id', 'name', 'email', 'custom_instructions', 'custom_response_style', 'enable_custom_instructions', 'custom_commands'])
            ]
        ]);
    }

    /**
     * Navigation vers une conversation spécifique avec ses messages
     */
    public function messages(Conversation $conversation)
    {
        // Pattern de sécurité répété
        if ($conversation->user_id !== auth()->id()) {
            abort(403);
        }

        $user = auth()->user();
        $conversations = Conversation::where('user_id', $user->id)
            ->orderBy('updated_at', 'desc')
            ->get();

        $messages = $conversation->messages()->orderBy('created_at')->get();

        $chatService = new ChatService();
        $models = $chatService->getModels();

        return Inertia::render('Ask/Index', [
            'conversations' => $conversations,
            'selectedConversation' => $conversation,
            'messages' => $messages,
            'models' => $models,
            'selectedModel' => $conversation->model,
            'auth' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name ?? '',
                    'email' => $user->email ?? '',
                    'custom_instructions' => $user->custom_instructions ?? '',
                    'custom_response_style' => $user->custom_response_style ?? '',
                    'enable_custom_instructions' => $user->enable_custom_instructions ?? true,
                    'custom_commands' => $user->custom_commands ?? '',
                ]
            ]
        ]);
    }

    /**
     * Mise à jour du modèle avec synchronisation utilisateur
     */
    public function updateModel(Request $request, Conversation $conversation)
    {
        $conversation->update(['model' => $request->model]);

        // Pattern de synchronisation : mise à jour de la préférence globale utilisateur
        auth()->user()->update(['model' => $request->model]);

        return back(); // Retour simple sans rechargement complet
    }
}
