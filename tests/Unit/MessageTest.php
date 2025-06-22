<?php

namespace Tests\Unit\Models;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_belongs_to_a_conversation()
    {
        $conversation = Conversation::factory()->create();
        $message = Message::factory()->create(['conversation_id' => $conversation->id]);

        $this->assertInstanceOf(Conversation::class, $message->conversation);
        $this->assertEquals($conversation->id, $message->conversation->id);
    }

    /** @test */
    public function it_can_belong_to_a_user()
    {
        $user = User::factory()->create();
        $message = Message::factory()->fromUser()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $message->user);
        $this->assertEquals($user->id, $message->user->id);
    }

    /** @test */
    public function it_can_be_from_assistant_without_user()
    {
        $message = Message::factory()->fromAssistant()->create();

        $this->assertNull($message->user);
        $this->assertEquals('assistant', $message->role);
    }

    /** @test */
    public function it_converts_to_api_format()
    {
        $message = Message::factory()->create([
            'role' => 'user',
            'content' => 'Hello, how are you?'
        ]);

        $apiFormat = $message->toApiFormat();

        $this->assertEquals([
            'role' => 'user',
            'content' => 'Hello, how are you?'
        ], $apiFormat);
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $message = new Message();

        $this->assertEquals(['conversation_id', 'user_id', 'role', 'content'], $message->getFillable());
    }

    /** @test */
    public function it_validates_role_enum()
    {
        $userMessage = Message::factory()->fromUser()->create();
        $assistantMessage = Message::factory()->fromAssistant()->create();

        $this->assertEquals('user', $userMessage->role);
        $this->assertEquals('assistant', $assistantMessage->role);
    }
}
