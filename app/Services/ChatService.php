<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ChatService
{
    private $baseUrl;
    private $apiKey;
    private $client;
    public const DEFAULT_MODEL = 'openai/gpt-4.1-mini';

    public function __construct()
    {
        $this->baseUrl = config('services.openrouter.base_url', 'https://openrouter.ai/api/v1');
        $this->apiKey = config('services.openrouter.api_key');
        $this->client = $this->createOpenAIClient();
    }

    /**
     * @return array<array-key, array{
     *     id: string,
     *     name: string,
     *     context_length: int,
     *     max_completion_tokens: int,
     *     pricing: array{prompt: int, completion: int}
     * }>
     */
    public function getModels(): array
    {
        return cache()->remember('openai.models', now()->addHour(), function () {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->get($this->baseUrl . '/models');

            return collect($response->json()['data'])
                ->sortBy('name')
                ->map(function ($model) {
                    return [
                        'id' => $model['id'],
                        'name' => $model['name'],
                        'context_length' => $model['context_length'],
                        'max_completion_tokens' => $model['top_provider']['max_completion_tokens'],
                        'pricing' => $model['pricing'],
                    ];
                })
                ->values()
                ->all()
            ;
        });
    }

    /**
     * @param array{role: 'user'|'assistant'|'system'|'function', content: string} $messages
     * @param string|null $model
     * @param float $temperature
     *
     * @return string
     */
    public function sendMessage(array $messages, string $model = null, float $temperature = 0.7): string
    {
        try {
            logger()->info('Envoi du message', [
                'model' => $model,
                'temperature' => $temperature,
            ]);

            $models = collect($this->getModels());
            if (!$model || !$models->contains('id', $model)) {
                $model = self::DEFAULT_MODEL;
                logger()->info('Modèle par défaut utilisé:', ['model' => $model]);
            }

            $messages = [$this->getChatSystemPrompt(), ...$messages];
            $response = $this->client->chat()->create([
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
            ]);

            logger()->info('Réponse reçue:', ['response' => $response]);

            $content = $response->choices[0]->message->content;

            return $content;
        } catch (\Exception $e) {
            logger()->error('Erreur dans sendMessage:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function createOpenAIClient(): \OpenAI\Client
    {
        return \OpenAI::factory()
            ->withApiKey($this->apiKey)
            ->withBaseUri($this->baseUrl)
            ->make()
        ;
    }

    /**
     * @return array{role: 'system', content: string}
     */
    private function getChatSystemPrompt(): array
    {
        $user = auth()->user();
        $now = now()->locale('fr')->format('l d F Y H:i');

        $basePrompt = "Tu es un assistant de chat. La date et l'heure actuelle est le {$now}. Tu es actuellement utilisé par {$user->name}.";

        // Ajouter les instructions personnalisées si elles existent et sont activées
        if ($user->enable_custom_instructions && ($user->custom_instructions || $user->custom_response_style)) {
            $customPrompt = "";

            if ($user->custom_instructions) {
                $customPrompt .= "\n\nInformations sur l'utilisateur :\n" . $user->custom_instructions;
            }

            if ($user->custom_response_style) {
                $customPrompt .= "\n\nStyle de réponse souhaité :\n" . $user->custom_response_style;
            }
            if ($user->custom_commands) {
                $customPrompt .= "\n\nCommandes personnalisées disponibles :\n" . $user->custom_commands;
                $customPrompt .= "\n\nQuand l'utilisateur utilise une commande (commençant par '/'), utilise la définition correspondante pour répondre de manière appropriée.";
            }

            $basePrompt .= $customPrompt;
        }

        return [
            'role' => 'system',
            'content' => $basePrompt,
        ];
    }

    /**
     * Génère un titre pour une conversation basé sur le premier message
     */
    public function generateTitle(string $firstMessage, string $model = null): string
    {
        try {
            $model = $model ?? self::DEFAULT_MODEL;

            $messages = [
                [
                    'role' => 'system',
                    'content' => 'Tu es un assistant qui génère des titres courts et pertinents pour des conversations. Génère un titre de maximum 40 caractères basé sur la réponse d\'une IA. Le titre doit capturer le sujet principal ou l\'essence de la réponse. Réponds uniquement avec le titre, sans guillemets ni ponctuation finale.'
                ],
                [
                    'role' => 'user',
                    'content' => "Génère un titre court et accrocheur pour cette conversation basé sur cette réponse d'IA : \"" . substr($firstMessage, 0, 500) . "\""
                ]
            ];

            $response = $this->client->chat()->create([
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.5, // Un peu moins créatif pour des titres plus cohérents
                'max_tokens' => 40
            ]);

            $title = trim($response->choices[0]->message->content);

            // Nettoyer le titre
            $title = str_replace(['"', "'", '«', '»', ':', '!', '?'], '', $title);

            // Limiter à 40 caractères max
            if (strlen($title) > 40) {
                $title = substr($title, 0, 37) . '...';
            }

            return $title ?: 'Nouvelle conversation';
        } catch (\Exception $e) {
            logger()->error('Erreur génération titre:', ['error' => $e->getMessage()]);

            // Titre de fallback basé sur les premiers mots de la réponse de l'ia
            $words = explode(' ', $firstMessage);
            $fallbackTitle = implode(' ', array_slice($words, 0, 5));

            return strlen($fallbackTitle) > 40
                ? substr($fallbackTitle, 0, 37) . '...'
                : $fallbackTitle;
        }
    }
}
