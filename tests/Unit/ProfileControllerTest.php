<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_get_custom_instructions()
    {
        $user = User::factory()->create([
            'custom_instructions' => 'Test instructions',
            'custom_response_style' => 'Test style',
            'enable_custom_instructions' => true,
            'custom_commands' => 'Test commands'
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('profile.get-custom-instructions'));

        $response->assertOk()
            ->assertJson([
                'custom_instructions' => 'Test instructions',
                'custom_response_style' => 'Test style',
                'enable_custom_instructions' => true,
                'custom_commands' => 'Test commands'
            ]);
    }

    /** @test */
    public function it_can_update_custom_instructions()
    {
        $user = User::factory()->create();

        $data = [
            'custom_instructions' => 'New instructions',
            'custom_response_style' => 'New style',
            'enable_custom_instructions' => false,
            'custom_commands' => 'New commands'
        ];

        $response = $this->actingAs($user)
            ->postJson(route('profile.custom-instructions'), $data);

        $response->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'custom_instructions' => 'New instructions',
            'custom_response_style' => 'New style',
            'enable_custom_instructions' => false,
            'custom_commands' => 'New commands'
        ]);
    }

    /** @test */
    public function it_validates_custom_instructions_length()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('profile.custom-instructions'), [
                'custom_instructions' => str_repeat('a', 1501), // Trop long
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['custom_instructions']);
    }

    /** @test */
    public function it_requires_authentication_for_custom_instructions()
    {
        $response = $this->getJson(route('profile.get-custom-instructions'));
        $response->assertStatus(401);

        $response = $this->postJson(route('profile.custom-instructions'), []);
        $response->assertStatus(401);
    }
}
