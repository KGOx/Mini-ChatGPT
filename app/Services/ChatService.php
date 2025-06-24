<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use OpenAI\Responses\StreamResponse;

class ChatService
{
    private $baseUrl;
    private $apiKey;
    private $client;
    // Modèle par défaut optimisé (GPT-4.1 mini via OpenRouter pour performance/coût)
    public const DEFAULT_MODEL = 'openai/gpt-4.1-mini';

    public function __construct()
    {
        // Utilisation d'OpenRouter.ai comme proxy unifié pour accéder à différents fournisseurs d'IA
        $this->baseUrl = config('services.openrouter.base_url', 'https://openrouter.ai/api/v1');
        $this->apiKey = config('services.openrouter.api_key');
        $this->client = $this->createOpenAIClient();
    }

    /**
     * Récupération des modèles disponibles avec cache pour optimiser les performances
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
        // Cache d'1 heure pour éviter les appels API répétés
        return cache()->remember('openai.models', now()->addHour(), function () {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->get($this->baseUrl . '/models');

            // Transformation et tri des modèles pour l'interface utilisateur
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
     * Envoi de message synchrone pour le mode classique (non-streaming)
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

            // Validation et fallback automatique vers le modèle par défaut
            $models = collect($this->getModels());
            if (!$model || !$models->contains('id', $model)) {
                $model = self::DEFAULT_MODEL;
                logger()->info('Modèle par défaut utilisé:', ['model' => $model]);
            }

            // Injection du prompt système avec instructions personnalisées en première position
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

    // Configuration du client OpenAI avec base URL personnalisée pour OpenRouter
    private function createOpenAIClient(): \OpenAI\Client
    {
        return \OpenAI::factory()
            ->withApiKey($this->apiKey)
            ->withBaseUri($this->baseUrl)
            ->make()
        ;
    }

    /**
     * Construction dynamique du prompt système avec intégration des instructions utilisateur
     * @return array{role: 'system', content: string}
     */
    private function getChatSystemPrompt(): array
    {
        $user = auth()->user();
        $now = now()->locale('fr')->format('l d F Y H:i');

        // Prompt de base avec contextualisation temporelle et utilisateur
        $basePrompt = "Tu es un assistant de chat. La date et l'heure actuelle est le {$now}. Tu es actuellement utilisé par {$user->name}.";

        // Injection conditionnelle des instructions personnalisées si activées
        if ($user->enable_custom_instructions && ($user->custom_instructions || $user->custom_response_style)) {
            $customPrompt = "";

            if ($user->custom_instructions) {
                $customPrompt .= "\n\nInformations sur l'utilisateur :\n" . $user->custom_instructions;
            }

            if ($user->custom_response_style) {
                $customPrompt .= "\n\nStyle de réponse souhaité :\n" . $user->custom_response_style;
            }

            // Système de commandes personnalisées avec parsing automatique
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
     * Génération automatique de titre basée sur la réponse de l'IA avec fallbacks robustes
     */
    public function generateTitle(string $firstMessage, string $model = null): string
    {
        try {
            $model = $model ?? self::DEFAULT_MODEL;

            // Prompt spécialisé pour génération de titres concis et pertinents
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
                'temperature' => 0.5, // Réduction de créativité pour cohérence
                'max_tokens' => 40
            ]);

            $title = trim($response->choices[0]->message->content);

            // Nettoyage et normalisation du titre généré
            $title = str_replace(['"', "'", '«', '»', ':', '!', '?'], '', $title);

            // Limitation stricte à 40 caractères pour l'interface
            if (strlen($title) > 40) {
                $title = substr($title, 0, 37) . '...';
            }

            return $title ?: 'Nouvelle conversation';
        } catch (\Exception $e) {
            logger()->error('Erreur génération titre:', ['error' => $e->getMessage()]);

            // Fallback intelligent basé sur les premiers mots de la réponse
            $words = explode(' ', $firstMessage);
            $fallbackTitle = implode(' ', array_slice($words, 0, 5));

            return strlen($fallbackTitle) > 40
                ? substr($fallbackTitle, 0, 37) . '...'
                : $fallbackTitle;
        }
    }

    /**
     * Méthode de streaming pour SSE - retourne un StreamResponse compatible
     *
     * @param array{role: 'assistant'|'function'|'system'|'user', content: string} $messages
     */
    public function stream(array $messages, ?string $model = null, float $temperature = 0.7): StreamResponse
    {
        try {
            logger()->info('Envoi du message en streaming', [
                'model' => $model,
                'temperature' => $temperature,
            ]);

            // Validation et fallback identique au mode synchrone
            $models = collect($this->getModels());
            if (!$model || !$models->contains('id', $model)) {
                $model = self::DEFAULT_MODEL;
                logger()->info('Modèle par défaut utilisé:', ['model' => $model]);
            }

            // Injection du prompt système en première position
            $messages = [$this->getChatSystemPrompt(), ...$messages];

            // Création du stream avec flag stream=true pour OpenAI API
            $stream = $this->client->chat()->createStreamed([
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
                'stream' => true,
            ]);

            return $stream;
        } catch (\Exception $e) {
            logger()->error('Erreur dans sendMessageStream:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
