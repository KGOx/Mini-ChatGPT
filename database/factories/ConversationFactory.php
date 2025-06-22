<?php

namespace Database\Factories;

use App\Models\User;
use App\Services\ChatService;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'model' => ChatService::DEFAULT_MODEL,
        ];
    }

    public function withoutTitle(): static
    {
        return $this->state(fn(array $attributes) => [
            'title' => null,
        ]);
    }

    public function withCustomModel(string $model): static
    {
        return $this->state(fn(array $attributes) => [
            'model' => $model,
        ]);
    }
}
