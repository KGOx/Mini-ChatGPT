<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateConversationTitle;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MessageController extends Controller
{
    public function store(Request $request, Conversation $conversation)
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        // Vérifier si c'est le premier message de la conversation
        $isFirstMessage = $conversation->messages()->count() === 0;
        $firstUserMessage = $request->content;

        // Ajouter le message de l'utilisateur
        $conversation->messages()->create([
            'user_id' => auth()->id(),
            'role' => 'user',
            'content' => $request->content,
        ]);

        // Appeler mon chatservice pour obtenir la réponse de l'ia
        $chatService = new ChatService();
        $response = $chatService->sendMessage(
            $conversation->messages->map(fn($m) => [
                'role' => $m->role,
                'content' => $m->content,
            ])->toArray(),
            $conversation->model
        );

        // Ajoutons la réponse de l'ia
        $conversation->messages()->create([
            'user_id' => null, // ou l'id d'un user assisant, au choix
            'role' => 'assistant',
            'content' => $response,
        ]);

        // Générer un titre basé sur la RÉPONSE de l'IA si c'est le premier échange
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

                // Titre de fallback basé sur la question de l'utilisateur
                $fallbackTitle = 'Discussion sur ' . substr($request->content, 0, 20) . '...';
                $conversation->update(['title' => $fallbackTitle]);
            }
        }

        // on met à jour la date de la conversation
        $conversation->touch();

        // Recharger TOUTES les conversations pour la sidebar
        $conversations = Conversation::where('user_id', auth()->id())
            ->orderBy('updated_at', 'desc')
            ->get();

        // Et on redirige ou on retourne la nouvelle liste de messages
        return Inertia::render('Ask/Index', [
            'messages' => $conversation->messages()->orderBy('created_at')->get(),
            'selectedConversation' => $conversation->fresh(), // Recharger pour avoir le nouveau titre
            'conversations' => $conversations,
            'generatedTitle' => $conversation->fresh()->title
        ]);
    }
    public function sendMessageStream(Request $request, Conversation $conversation)
    {
        if ($conversation->user_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'content' => 'required|string', // Adaptez selon votre frontend
        ]);

        $isFirstMessage = $conversation->messages()->count() === 0;

        // Créer le message utilisateur
        $conversation->messages()->create([
            'user_id' => auth()->id(),
            'role' => 'user',
            'content' => $request->content,
        ]);

        // Préparer les messages pour l'API
        $apiMessages = $conversation
            ->messages()
            ->get()
            ->map(fn(Message $message) => $message->toApiFormat())
            ->toArray();

        return response()->stream(function () use ($conversation, $apiMessages, $isFirstMessage, $request) {
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

                foreach ($stream as $response) {
                    $content = $response->choices[0]->delta->content ?? '';
                    $fullResponse .= $content;

                    if (!empty($content)) {
                        echo "data: " . json_encode(['content' => $content]) . "\n\n";
                        ob_flush(); // ← CRUCIAL pour forcer l'envoi immédiat
                        flush();

                        // Micro-pause pour éviter la surcharge
                        usleep(100000); // 100ms
                    }
                }

                // Sauvegarder la réponse complète
                $conversation->messages()->create([
                    'user_id' => null,
                    'role' => 'assistant',
                    'content' => $fullResponse,
                ]);

                if ($isFirstMessage && !$conversation->title) {
                    try {
                        $title = $chatService->generateTitle($fullResponse, $conversation->model);
                        $conversation->update(['title' => $title]);

                        // On envoie le titre via SSE
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

                        // Envoyer le titre de fallback via SSE
                        echo "data: " . json_encode(['title' => $fallbackTitle]) . "\n\n";
                        ob_flush();
                        flush();
                    }
                }

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
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // ← CRUCIAL pour Nginx
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Headers' => 'Cache-Control',
        ]);
    }
}
