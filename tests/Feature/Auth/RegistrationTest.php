<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\VerificationCode;
use App\Mail\VerificationOtpMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_redirects_without_token(): void
    {
        $response = $this->get('/register');

        $response->assertRedirect('/');
        $response->assertSessionHas('error');
    }

    public function test_registration_screen_can_be_rendered_with_valid_token(): void
    {
        $email = 'merchant@example.com';
        $token = 'test-token-12345';

        VerificationCode::create([
            'email' => $email,
            'code' => '123456',
            'type' => 'register',
            'token' => $token,
            'expires_at' => now()->addHour(),
        ]);

        $response = $this->get("/register?email={$email}&token={$token}");

        $response->assertRedirect('/?action=register&email=merchant%40example.com&otp_token=test-token-12345');
    }

    public function test_activation_link_can_be_requested(): void
    {
        Mail::fake();

        $response = $this->post('/register/request-link', [
            'email' => 'new-merchant@example.com',
        ]);

        $response->assertSessionHas('status', 'activation-sent');
        $response->assertSessionHas('email', 'new-merchant@example.com');

        $this->assertDatabaseHas('verification_codes', [
            'email' => 'new-merchant@example.com',
            'type' => 'register',
        ]);

        Mail::assertSent(VerificationOtpMail::class, function ($mail) {
            return $mail->email === 'new-merchant@example.com' && $mail->type === 'register';
        });
    }

    public function test_new_users_cannot_register_with_invalid_otp(): void
    {
        $email = 'merchant@example.com';
        $token = 'test-token-12345';

        VerificationCode::create([
            'email' => $email,
            'code' => '123456',
            'type' => 'register',
            'token' => $token,
            'expires_at' => now()->addHour(),
        ]);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => $email,
            'password' => 'password',
            'password_confirmation' => 'password',
            'code' => '999999', // Incorrect OTP
            'token' => $token,
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_new_users_can_register_with_valid_otp(): void
    {
        $email = 'merchant@example.com';
        $token = 'test-token-12345';

        VerificationCode::create([
            'email' => $email,
            'code' => '123456',
            'type' => 'register',
            'token' => $token,
            'expires_at' => now()->addHour(),
        ]);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => $email,
            'password' => 'password',
            'password_confirmation' => 'password',
            'code' => '123456',
            'token' => $token,
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseMissing('verification_codes', [
            'email' => $email,
            'type' => 'register',
        ]);
    }
}
