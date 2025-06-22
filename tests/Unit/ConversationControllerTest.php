<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ConversationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock ChatService pour éviter les appels API
        $this->mock(ChatService::class, function ($mock) {
            $mock->shouldReceive('getModels')->andReturn([
                ['id' => 'gpt-3.5-turbo', 'name' => 'GPT-3.5 Turbo'],
                ['id' => 'gpt-4', 'name' => 'GPT-4'],
            ]);
        });
    }

    /** @test */
    public function it_creates_new_conversation_on_ask_page()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('ask.index'));

        $response->assertOk();

        $this->assertDatabaseHas('conversations', [
            'user_id' => $user->id,
            'title' => null
        ]);
    }

    /** @test */
    public function it_cleans_up_empty_conversations_before_creating_new_one()
    {
        $user = User::factory()->create();

        // Créer une conversation vide ancienne
        $emptyConversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()->subMinutes(5)
        ]);

        $response = $this->actingAs($user)->get(route('ask.index'));

        $this->assertDatabaseMissing('conversations', ['id' => $emptyConversation->id]);
    }

    /** @test */
    public function it_can_store_new_conversation()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('conversations.store'));

        $response->assertOk();

        $this->assertDatabaseHas('conversations', [
            'user_id' => $user->id
        ]);
    }

    /** @test */
    public function it_can_show_conversation_messages()
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);
        Message::factory()->count(3)->create(['conversation_id' => $conversation->id]);

        $response = $this->actingAs($user)
            ->get(route('conversations.messages', $conversation));

        $response->assertOk();
    }

    /** @test */
    public function it_prevents_unauthorized_access_to_conversations()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $user1->id]);

        $response = $this->actingAs($user2)
            ->get(route('conversations.messages', $conversation));

        $response->assertStatus(403);
    }

    /** @test */
    public function it_can_delete_conversation()
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->deleteJson(route('conversations.destroy', $conversation));

        $response->assertOk();
        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
    }

    /** @test */
    public function it_can_update_conversation_model()
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->patchJson(route('conversations.updateModel', $conversation), [
                'model' => 'gpt-4'
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'model' => 'gpt-4'
        ]);
    }
}
