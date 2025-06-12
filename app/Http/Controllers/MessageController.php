<?php

namespace App\Http\Controllers;

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

        // on met à jour la date de la conversation
        $conversation->touch();

        // Et on redirige ou on retourne la nouvelle liste de messages
        return Inertia::render('Ask/Index', [
            'messages' => $conversation->messages()->orderBy('created_at')->get()
        ]);
    }
}
