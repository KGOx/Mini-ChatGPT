<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateConversationTitle;
use App\Models\Conversation;
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
        $chatService = new \App\Services\ChatService();
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

        // Et on redirige ou on retourne la nouvelle liste de messages
        return Inertia::render('Ask/Index', [
            'messages' => $conversation->messages()->orderBy('created_at')->get(),
            'selectedConversation' => $conversation->fresh(), // Recharger pour avoir le nouveau titre
        ]);
    }
}
