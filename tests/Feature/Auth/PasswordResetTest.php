<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\VerificationCode;
use App\Mail\VerificationOtpMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_screen_redirects_to_welcome_page(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertRedirect('/admin/login?action=forgot-password');
    }

    public function test_password_reset_code_can_be_requested(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $response = $this->post('/forgot-password', ['email' => $user->email]);

        $response->assertSessionHas('status', 'code-sent');
        $response->assertSessionHas('email', $user->email);

        $this->assertDatabaseHas('verification_codes', [
            'email' => $user->email,
            'type' => 'password_reset',
        ]);

        Mail::assertSent(VerificationOtpMail::class, function ($mail) use ($user) {
            return $mail->email === $user->email && $mail->type === 'password_reset';
        });
    }

    public function test_password_cannot_be_reset_with_invalid_code(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('old-password'),
        ]);

        VerificationCode::create([
            'email' => $user->email,
            'code' => '123456',
            'type' => 'password_reset',
            'expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->post('/reset-password-otp', [
            'email' => $user->email,
            'code' => '999999', // Invalid code
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_password_can_be_reset_with_valid_code(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('old-password'),
        ]);

        VerificationCode::create([
            'email' => $user->email,
            'code' => '123456',
            'type' => 'password_reset',
            'expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->post('/reset-password-otp', [
            'email' => $user->email,
            'code' => '123456',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertRedirect('/admin/login');
        $response->assertSessionHas('status', 'password-updated');

        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check('new-password123', $user->fresh()->password)
        );

        $this->assertDatabaseMissing('verification_codes', [
            'email' => $user->email,
            'type' => 'password_reset',
        ]);
    }

    public function test_otp_generation_is_rate_limited(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        // 1st request should succeed
        $response1 = $this->post('/forgot-password', ['email' => $user->email]);
        $response1->assertSessionHas('status', 'code-sent');

        // 2nd request (within 1 minute) should trigger minute rate limit
        $response2 = $this->post('/forgot-password', ['email' => $user->email]);
        $response2->assertSessionHasErrors('email');
        $this->assertStringContainsString('الانتظار دقيقة واحدة', session('errors')->first('email'));
    }

    public function test_otp_generation_hourly_limit(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        // Simulate 3 sends spread across the hour (each separated by 5 minutes so minute rate limit is bypassed)
        for ($i = 1; $i <= 3; $i++) {
            \Illuminate\Support\Facades\DB::table('verification_codes')->insert([
                'email' => $user->email,
                'code' => '11111' . $i,
                'type' => 'password_reset',
                'expires_at' => now()->addMinutes(15),
                'created_at' => now()->subMinutes(15 - $i * 2),
                'updated_at' => now()->subMinutes(15 - $i * 2),
            ]);
        }

        // The 4th request should trigger hourly rate limit
        $response = $this->post('/forgot-password', ['email' => $user->email]);
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('3 مرات في الساعة', session('errors')->first('email'));
    }
}
