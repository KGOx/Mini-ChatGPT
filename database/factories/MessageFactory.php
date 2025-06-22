<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'user_id' => User::factory(),
            'role' => 'user',
            'content' => $this->faker->paragraph(),
        ];
    }

    public function fromUser(): static
    {
        return $this->state(fn(array $attributes) => [
            'role' => 'user',
            'user_id' => User::factory(),
        ]);
    }

    public function fromAssistant(): static
    {
        return $this->state(fn(array $attributes) => [
            'role' => 'assistant',
            'user_id' => null,
        ]);
    }

    public function withContent(string $content): static
    {
        return $this->state(fn(array $attributes) => [
            'content' => $content,
        ]);
    }
}
