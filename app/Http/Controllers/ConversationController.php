<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Schema;

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

        // On nettoie les conversations vides de l'utilisateur avant la création d'une nouvelle
        Conversation::cleanupEmpty($user->id);

        $newConversation = Conversation::create([
            'user_id' => $user->id,
            'model' => $user->model ?? ChatService::DEFAULT_MODEL
        ]);

        $conversations = Conversation::where('user_id', $user->id)
            ->orderBy('updated_at', 'desc')
            ->get();

        $selectedConversation = $newConversation;
        $messages = collect();

        $chatService = new ChatService();
        $models = $chatService->getModels();



        return Inertia::render('Ask/Index', [
            'conversations' => $conversations,
            'selectedConversation' => $selectedConversation,
            'messages' => $messages,
            'models' => $models,
            'selectedModel' => $newConversation->model,
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

    public function store()
    {
        $user = auth()->user();

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
            'messages' => collect(),
            'models' => $models,
            'selectedModel' => $conversation->model,
            'auth' => [
                'user' => $user->only(['id', 'name', 'email', 'custom_instructions', 'custom_response_style', 'enable_custom_instructions', 'custom_commands'])
            ]
        ]);
    }
    public function show(Conversation $conversation)
    {
        // Vérifier que l'utilisateur peut voir cette conversation
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
                'user' => $user->only(['id', 'name', 'email', 'custom_instructions', 'custom_response_style', 'enable_custom_instructions', 'custom_commands'])
            ]
        ]);
    }

    public function destroy(Conversation $conversation)
    {
        // Vérifier que l'utilisateur peut supprimer cette conversation
        if ($conversation->user_id !== auth()->id()) {
            abort(403);
        }

        $user = auth()->user();
        $conversationId = $conversation->id;
        $conversation->delete();

        // Retourner les données mises à jour au lieu d'une simple redirection
        $conversations = Conversation::where('user_id', auth()->id())
            ->orderBy('updated_at', 'desc')
            ->get();

        // Si il reste des conversations, prendre la première
        $selectedConversation = $conversations->first();

        // Si aucune conversation, créer une nouvelle
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
            'deletedConversationId' => $conversationId, // Pour info côté frontend
            'auth' => [
                'user' => $user->only(['id', 'name', 'email', 'custom_instructions', 'custom_response_style', 'enable_custom_instructions', 'custom_commands'])
            ]
        ]);
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

    public function updateModel(Request $request, Conversation $conversation)
    {
        $conversation->update(['model' => $request->model]);

        // on met à jour le modèle de l'utilisateur
        auth()->user()->update(['model' => $request->model]);

        return back();
    }
}
