<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Sauvegarde des instructions personnalisées avec validation stricte des limites
     */
    public function updateCustomInstructions(Request $request)
    {
        $validated = $request->validate([
            'custom_instructions' => 'nullable|string|max:1500',
            'custom_response_style' => 'nullable|string|max:1500',
            'enable_custom_instructions' => 'boolean',
            'custom_commands' => 'nullable|string|max:2000', // Limite étendue pour les commandes multiples
        ]);

        // Mise à jour directe via l'utilisateur authentifié (sécurité implicite)
        auth()->user()->update($validated);

        // Retour avec flash message pour feedback utilisateur via Inertia
        return back()->with('success', 'Instructions et commandes personnalisées mises à jour avec succès.');
    }

    /**
     * API JSON pour récupération des instructions (utilisée par le frontend Vue.js)
     */
    public function getCustomInstructions()
    {
        $user = auth()->user();

        // Retour JSON avec fallbacks pour champs potentiellement null
        return response()->json([
            'custom_instructions' => $user->custom_instructions ?? '',
            'custom_response_style' => $user->custom_response_style ?? '',
            'enable_custom_instructions' => $user->enable_custom_instructions ?? true, // Activé par défaut
            'custom_commands' => $user->custom_commands ?? '',
        ]);
    }
}
