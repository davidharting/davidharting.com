<?php

namespace Tests\Feature;

use App\Models\MemoryParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_page_is_titled_account_and_offers_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')
            ->assertSeeTextInOrder(['Account - davidharting.com', 'Account'])
            ->assertSee(route('logout'));
    }

    public function test_profile_page_links_the_admin_to_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/profile')
            ->assertSee(route('admin.index'));
    }

    public function test_profile_page_does_not_link_anyone_else_to_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')
            ->assertDontSee(route('admin.index'));
    }

    public function test_profile_page_offers_to_delete_the_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')
            ->assertSeeText('Delete Your Account');
    }

    public function test_profile_page_does_not_offer_a_memories_participant_to_delete_their_account(): void
    {
        $user = User::factory()->create();
        MemoryParticipant::create(['user_id' => $user->id]);

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertDontSeeText('Delete Your Account');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
