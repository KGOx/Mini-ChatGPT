<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateConversationTitle;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Contrôleur spécialisé pour la gestion des messages et du streaming SSE
 * Implémente les deux modes : classique (rechargement) et streaming (temps réel)
 */
class MessageController extends Controller
{
    /**
     * Mode classique : traitement synchrone avec rechargement de page
     */
    public function store(Request $request, Conversation $conversation)
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        // Détection du premier message pour logique de génération de titre
        $isFirstMessage = $conversation->messages()->count() === 0;
        $firstUserMessage = $request->content;

        // Persistance immédiate du message utilisateur
        $conversation->messages()->create([
            'user_id' => auth()->id(),
            'role' => 'user',
            'content' => $request->content,
        ]);

        // Appel synchrone du service IA avec historique complet
        $chatService = new ChatService();
        $response = $chatService->sendMessage(
            $conversation->messages->map(fn($m) => [
                'role' => $m->role,
                'content' => $m->content,
            ])->toArray(),
            $conversation->model
        );

        // Persistance de la réponse IA (user_id null pour distinction)
        $conversation->messages()->create([
            'user_id' => null,
            'role' => 'assistant',
            'content' => $response,
        ]);

        // Logique de génération de titre avec fallback robuste
        if ($isFirstMessage && !$conversation->title) {
            try {
                $title = $chatService->generateTitle($response, $conversation->model);
                $conversation->update(['title' => $title]);
                logger()->info('Titre généré:', ['conversation_id' => $conversation->id, 'title' => $title]);
            } catch (\Exception $e) {
                logger()->error('Erreur génération titre conversation:', [
                    'conversation_id' => $conversation->id,
                    'error' => $e->getMessage()
                ]);

                // Fallback intelligent basé sur la question utilisateur
                $fallbackTitle = 'Discussion sur ' . substr($request->content, 0, 20) . '...';
                $conversation->update(['title' => $fallbackTitle]);
            }
        }

        // Touch pour mettre à jour timestamp et tri de la sidebar
        $conversation->touch();

        // Rechargement complet des conversations pour synchronisation sidebar
        $conversations = Conversation::where('user_id', auth()->id())
            ->orderBy('updated_at', 'desc')
            ->get();

        return Inertia::render('Ask/Index', [
            'messages' => $conversation->messages()->orderBy('created_at')->get(),
            'selectedConversation' => $conversation->fresh(), // Fresh pour récupérer le titre généré
            'conversations' => $conversations,
            'generatedTitle' => $conversation->fresh()->title
        ]);
    }

    /**
     * Mode streaming : SSE temps réel avec gestion du buffering PHP
     */
    public function sendMessageStream(Request $request, Conversation $conversation)
    {
        if ($conversation->user_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'content' => 'required|string',
        ]);

        $isFirstMessage = $conversation->messages()->count() === 0;

        // Persistance immédiate du message utilisateur
        $conversation->messages()->create([
            'user_id' => auth()->id(),
            'role' => 'user',
            'content' => $request->content,
        ]);

        // Transformation en format API via méthode dédiée du modèle
        $apiMessages = $conversation
            ->messages()
            ->get()
            ->map(fn(Message $message) => $message->toApiFormat())
            ->toArray();

        // Streaming SSE avec closure et gestion du buffering
        return response()->stream(function () use ($conversation, $apiMessages, $isFirstMessage, $request) {
            // Configuration PHP pour streaming temps réel
            set_time_limit(0);
            ignore_user_abort(true);
            ini_set('output_buffering', 'off');
            ini_set('zlib.output_compression', false);
            $fullResponse = '';

            try {
                $chatService = new ChatService();
                $stream = $chatService->stream(
                    messages: $apiMessages,
                    model: $conversation->model,
                    temperature: $conversation->getTemperature()
                );

                // Boucle de traitement du stream avec accumulation
                foreach ($stream as $response) {
                    $content = $response->choices[0]->delta->content ?? '';
                    $fullResponse .= $content;

                    if (!empty($content)) {
                        echo "data: " . json_encode(['content' => $content]) . "\n\n";
                        ob_flush(); // Technique cruciale pour forcer l'envoi immédiat
                        flush();

                        // Micro-pause pour éviter la surcharge serveur/client
                        usleep(100000); // 100ms
                    }
                }

                // Persistance de la réponse complète après streaming
                $conversation->messages()->create([
                    'user_id' => null,
                    'role' => 'assistant',
                    'content' => $fullResponse,
                ]);

                // Génération et envoi de titre via SSE pour mise à jour temps réel
                if ($isFirstMessage && !$conversation->title) {
                    try {
                        $title = $chatService->generateTitle($fullResponse, $conversation->model);
                        $conversation->update(['title' => $title]);

                        // Envoi du titre via SSE pour mise à jour sidebar temps réel
                        echo "data: " . json_encode(['title' => $title]) . "\n\n";
                        ob_flush();
                        flush();

                        logger()->info('Titre généré:', ['conversation_id' => $conversation->id, 'title' => $title]);
                    } catch (\Exception $e) {
                        logger()->error('Erreur génération titre conversation:', [
                            'conversation_id' => $conversation->id,
                            'error' => $e->getMessage()
                        ]);

                        $fallbackTitle = 'Discussion sur ' . substr($request->content, 0, 20) . '...';
                        $conversation->update(['title' => $fallbackTitle]);

                        // Envoi du fallback via SSE
                        echo "data: " . json_encode(['title' => $fallbackTitle]) . "\n\n";
                        ob_flush();
                        flush();
                    }
                }

                // Signal de fin de stream pour client JavaScript
                echo "data: [DONE]\n\n";
                ob_flush();
                flush();
            } catch (\Exception $e) {
                logger()->error('Erreur streaming:', ['error' => $e->getMessage()]);
                echo "data: " . json_encode(['error' => 'Erreur de streaming']) . "\n\n";
                ob_flush();
                flush();
            }
        }, 200, [
            // Headers SSE essentiels pour streaming temps réel
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Crucial pour Nginx/Apache
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Headers' => 'Cache-Control',
        ]);
    }
}
