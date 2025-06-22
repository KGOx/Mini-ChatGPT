<?php

namespace Tests\Unit\Models;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_belongs_to_a_user()
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $conversation->user);
        $this->assertEquals($user->id, $conversation->user->id);
    }

    /** @test */
    public function it_has_many_messages()
    {
        $conversation = Conversation::factory()->create();
        Message::factory()->count(3)->create(['conversation_id' => $conversation->id]);

        $this->assertCount(3, $conversation->messages);
        $this->assertInstanceOf(Message::class, $conversation->messages->first());
    }

    /** @test */
    public function it_can_check_if_empty()
    {
        $conversation = Conversation::factory()->create();
        $this->assertTrue($conversation->isEmpty());

        Message::factory()->create(['conversation_id' => $conversation->id]);
        $conversation->refresh();
        $this->assertFalse($conversation->isEmpty());
    }

    /** @test */
    public function it_can_cleanup_empty_conversations()
    {
        $user = User::factory()->create();

        // Conversation vide (ancienne)
        $emptyConversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()->subMinutes(5)
        ]);

        // Conversation avec messages
        $fullConversation = Conversation::factory()->create(['user_id' => $user->id]);
        Message::factory()->create(['conversation_id' => $fullConversation->id]);

        // Conversation vide récente
        $recentEmptyConversation = Conversation::factory()->create(['user_id' => $user->id]);

        Conversation::cleanupEmpty($user->id);

        $this->assertDatabaseMissing('conversations', ['id' => $emptyConversation->id]);
        $this->assertDatabaseHas('conversations', ['id' => $fullConversation->id]);
        $this->assertDatabaseHas('conversations', ['id' => $recentEmptyConversation->id]);
    }

    /** @test */
    public function it_returns_default_temperature()
    {
        $conversation = Conversation::factory()->create();

        $this->assertEquals(0.7, $conversation->getTemperature());
    }

    /** @test */
    public function it_has_fillable_attributes()
    {
        $conversation = new Conversation();

        $this->assertEquals(['user_id', 'title', 'model'], $conversation->getFillable());
    }
}
