<?php

namespace App\Http\Controllers;

use App\Services\ChatService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Contrôleur pour système de chat simple (one-shot, sans persistance)
 * Alternative au système complet ConversationController + MessageController
 */
class AskController extends Controller
{
    /**
     * Interface de test pour modèles d'IA sans création de conversation
     */
    public function index()
    {
        // Récupération des modèles disponibles via le service centralisé
        $models = (new ChatService())->getModels();
        $selectedModel = ChatService::DEFAULT_MODEL;

        return Inertia::render('Ask/Index', [
            'models' => $models,
            'selectedModel' => $selectedModel,
        ]);
    }

    /**
     * Chat simple : question unique → réponse via flash message (pas de persistence)
     */
    public function ask(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
            'model' => 'required|string',
        ]);

        try {
            // Format minimal pour test rapide (pas de prompt système complexe)
            $messages = [[
                'role' => 'user',
                'content' => $request->message,
            ]];

            // Appel synchrone du service (pas de streaming)
            $response = (new ChatService())->sendMessage(
                messages: $messages,
                model: $request->model
            );

            // Retour via flash message pour affichage simple
            return redirect()->back()->with('message', $response);
        } catch (\Exception $e) {
            // Gestion d'erreur avec message utilisateur friendly
            return redirect()->back()->with('error', 'Erreur: ' . $e->getMessage());
        }
    }
}
