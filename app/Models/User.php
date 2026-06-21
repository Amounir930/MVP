<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Traits\BelongsToTenant;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'tenant_id', 'role', 'is_admin'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, BelongsToTenant;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Send an OTP verification code with strict rate limits (1 per min, 3 per hour).
     */
    public static function sendOtpCode(string $email, string $type, bool $generateToken = false): array
    {
        $oneMinuteAgo = now()->subMinute();
        $oneHourAgo = now()->subHour();

        $recentInMinute = \App\Models\VerificationCode::where('email', $email)
            ->where('created_at', '>=', $oneMinuteAgo)
            ->exists();

        if ($recentInMinute) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => ['يرجى الانتظار دقيقة واحدة قبل طلب رمز جديد.'],
            ]);
        }

        $recentInHourCount = \App\Models\VerificationCode::where('email', $email)
            ->where('created_at', '>=', $oneHourAgo)
            ->count();

        if ($recentInHourCount >= 3) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => ['لقد تجاوزت الحد الأقصى لإرسال الرموز (3 مرات في الساعة). يرجى المحاولة لاحقاً.'],
            ]);
        }

        // Clean up expired verification codes
        \App\Models\VerificationCode::where('email', $email)
            ->where('expires_at', '<', now())
            ->delete();

        $code = (string) rand(100000, 999999);
        $token = $generateToken ? \Illuminate\Support\Str::random(64) : null;
        $expiresAt = now()->addMinutes($type === 'register' ? 60 : 15);

        \App\Models\VerificationCode::create([
            'email' => $email,
            'code' => $code,
            'type' => $type,
            'token' => $token,
            'expires_at' => $expiresAt,
        ]);

        \Illuminate\Support\Facades\Mail::to($email)->send(
            new \App\Mail\VerificationOtpMail($email, $code, $type, $token)
        );

        return [
            'code' => $code,
            'token' => $token,
        ];
    }

    /**
     * Send OTP verification code for the current user instance.
     */
    public function sendOtpCodeForUser(string $type): array
    {
        return self::sendOtpCode($this->email, $type, false);
    }
}
