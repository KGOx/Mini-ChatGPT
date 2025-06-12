<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Services\ChatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateConversationTitle implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private Conversation $conversation,
        private string $firstMessage
    ) {}

    public function handle(): void
    {
        if ($this->conversation->title) {
            return; // Déjà un titre
        }

        $chatService = new ChatService();
        $title = $chatService->generateTitle($this->firstMessage, $this->conversation->model);

        $this->conversation->update(['title' => $title]);
    }
}
