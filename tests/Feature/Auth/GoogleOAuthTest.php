<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleOAuthTest extends TestCase
{
    use RefreshDatabase;

    private function mockGoogleUser(string $id, string $email, string $name): void
    {
        $googleUser = (new SocialiteUser)->map([
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'avatar' => 'https://example.com/avatar.png',
        ]);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_redirect_route_sends_user_to_google()
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirect')
            ->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get(route('google.redirect'));

        $response->assertRedirect('https://accounts.google.com/o/oauth2/auth');
    }

    public function test_callback_creates_a_new_user_and_logs_in()
    {
        $this->mockGoogleUser('google-123', 'nuevo@example.com', 'Nuevo Usuario');

        $response = $this->get(route('google.callback'));

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'nuevo@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('google-123', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->password);
    }

    public function test_callback_links_google_to_existing_user_by_email()
    {
        $user = User::factory()->create(['email' => 'existente@example.com']);

        $this->mockGoogleUser('google-456', 'existente@example.com', 'Usuario Existente');

        $this->get(route('google.callback'));

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertSame('google-456', $user->fresh()->google_id);
    }

    public function test_callback_logs_in_existing_google_user()
    {
        $user = User::factory()->create([
            'email' => 'google@example.com',
            'google_id' => 'google-789',
        ]);

        $this->mockGoogleUser('google-789', 'google@example.com', 'Google User');

        $this->get(route('google.callback'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::query()->count());
    }

    public function test_authenticated_users_cannot_access_oauth_routes()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('google.redirect'))
            ->assertRedirect(route('dashboard', absolute: false));
    }
}
