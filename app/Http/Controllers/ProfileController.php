<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function updateCustomInstructions(Request $request)
    {
        $validated = $request->validate([
            'custom_instructions' => 'nullable|string|max:1500',
            'custom_response_style' => 'nullable|string|max:1500',
            'enable_custom_instructions' => 'boolean'
        ]);

        auth()->user()->update($validated);

        return back()->with('success', 'Instructions personnalisées mises à jour avec succès.');
    }

    public function getCustomInstructions()
    {
        $user = auth()->user();

        return response()->json([
            'custom_instructions' => $user->custom_instructions ?? '',
            'custom_response_style' => $user->custom_response_style ?? '',
            'enable_custom_instructions' => $user->enable_custom_instructions ?? true,
        ]);
    }
}
