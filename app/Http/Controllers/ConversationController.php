<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ConversationController extends Controller
{
    public function index()
    {
        // Redirige vers la page principale du chat
        return redirect()->route('ask.index');
    }
    public function ask()
    {
        $user = auth()->user();
        $chatService = new \App\Services\ChatService();

        $defaultModel = \App\Services\ChatService::DEFAULT_MODEL;

        $conversations = Conversation::where('user_id', $user->id)
            ->orderBy('updated_at', 'desc')
            ->get();

        $selectedConversation = $conversations->first();

        // Si aucune conversation n'existe, en créer une automatiquement
        if (!$selectedConversation) {
            $selectedConversation = Conversation::create([
                'user_id' => $user->id,
                'model' => $user->model ?? $defaultModel
            ]);
            $conversations = $conversations->push($selectedConversation);
        }

        $messages = $selectedConversation->messages()->orderBy('created_at')->get();

        $chatService = new \App\Services\ChatService();
        $models = $chatService->getModels();

        return Inertia::render('Ask/Index', [
            'conversations' => $conversations,
            'selectedConversation' => $selectedConversation,
            'messages' => $messages,
            'models' => $models,
            'selectedModel' => $user->model ?? $models[0]['id'],
        ]);
    }

    public function store()
    {
        $conversation = Conversation::create([
            'user_id' => auth()->id(),
            'model' => auth()->user()->model ?? ChatService::DEFAULT_MODEL
        ]);

        $conversations = Conversation::where('user_id', auth()->id())
            ->orderBy('updated_at', 'desc')
            ->get();

        $chatService = new \App\Services\ChatService();
        $models = $chatService->getModels();

        return Inertia::render('Ask/Index', [
            'conversations' => $conversations,
            'selectedConversation' => $conversation,
            'messages' => collect(),
            'models' => $models,
            'selectedModel' => auth()->user()->model ?? $models[0]['id'],
        ]);
    }

    public function destroy(Conversation $conversation)
    {
        // Vérifier que l'utilisateur peut supprimer cette conversation
        if ($conversation->user_id !== auth()->id()) {
            abort(403);
        }

        $conversation->delete();

        // Rediriger vers /ask pour recharger la liste
        return redirect()->route('ask.index');
    }

    public function messages(Conversation $conversation)
    {
        // On vérifie que l'utilisateur peut voir la conversation
        if ($conversation->user_id !== auth()->id()) {
            abort(403);
        }

        $user = auth()->user();
        $conversations = Conversation::where('user_id', $user->id)
            ->orderBy('updated_at', 'desc')
            ->get();

        $messages = $conversation->messages()->orderBy('created_at')->get();

        $chatService = new \App\Services\ChatService();
        $models = $chatService->getModels();

        return Inertia::render('Ask/Index', [
            'conversations' => $conversations,
            'selectedConversation' => $conversation,
            'messages' => $messages,
            'models' => $models,
            'selectedModel' => $conversation->model,
        ]);
    }

    public function updateModel(Request $request, Conversation $conversation)
    {
        $conversation->update(['model' => $request->model]);

        // on met à jour le modèle de l'utilisateur
        auth()->user()->update(['model' => $request->model]);

        return back();
    }
}
