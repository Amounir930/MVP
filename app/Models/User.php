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

        $oneDayAgo = now()->subDay();
        $recentInDayCount = \App\Models\VerificationCode::where('email', $email)
            ->where('created_at', '>=', $oneDayAgo)
            ->count();

        if ($recentInDayCount >= 7) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => ['لقد تجاوزت الحد الأقصى لإرسال الرموز (7 مرات في اليوم). يرجى المحاولة غداً.'],
            ]);
        }

        // Invalidate all previous active codes for this email and type by expiring them immediately (burning the old code)
        \App\Models\VerificationCode::where('email', $email)
            ->where('type', $type)
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()->subSecond()]);

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

    public static function isDisposableEmail(string $email): bool
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        $domain = strtolower(substr(strrchr($email, "@"), 1));
        
        $disposableDomains = [
            'temp-mail.org', 'tempmail.com', 'temp-mail.com', 'temp-mail.ru', 'tempmail.net',
            'yopmail.com', 'yopmail.fr', 'yopmail.net', 'cool.fr.nf', 'jetable.fr.nf', 'courriel.fr.nf',
            'mailinator.com', 'mailinator.net', 'mailinator2.com',
            'guerrillamail.com', 'guerrillamail.net', 'guerrillamail.org', 'guerrillamail.biz', 'grr.la', 'guerillamail.info', 'guerillamailblock.com', 'pokemail.net', 'spam4.me',
            '10minutemail.com', '10minutemail.net', '10minutemail.co.za', '10minutemail.be',
            'trashmail.com', 'trashmail.net', 'trashmail.me',
            'sharklasers.com', 'maildrop.cc', 'mailnesia.com', 'mailcatch.com', 'mailnull.com',
            'dispostable.com', 'getairmail.com', 'throwawaymail.com', 'tempmailaddress.com',
            'generator.email', 'temporary-mail.net', 'tempmailo.com', 'tempr.email',
            'mohmal.com', 'mohmal.in', 'tempmail.dev', 'emailondeck.com', 'burnermail.io',
            'aratrin.com', 'boun.cr', 'safetymail.info', 'inboxkitten.com', 'disposable.com',
            'mintemail.com', 'spambox.us', 'fakeinbox.com', 'mytrashmail.com', '027168.com',
            'crazymailing.com', 'zillamail.com', 'mailzilla.org', 'inboxkitten.com',
            'tempmail.live', 'tempmail.ninja', 'tempmail.red', 'tempmail.cash', 'tempmail.lol',
            'temp-mail.io', 'tempmail.host', 'tempmail.top', 'tempmail.website', 'disposablemail.com',
            'fakemailgenerator.com', 'guerrillamail.de', 'tmpmail.org', 'tmpmail.com', 'tmpmail.net',
            'mailtemp.org', 'mailtemp.net', 'mailtemp.com', 'mailtostemp.com', 'mailtostemp.org',
            'mailtostemp.net', 'mailtostemp.dev', 'mailtostemp.guru', 'mailtostemp.club',
            'mailtostemp.asia', 'mailtostemp.us', 'mailtostemp.co'
        ];

        if (in_array($domain, $disposableDomains)) {
            return true;
        }

        // Match common spam/disposable keywords in domain name
        if (preg_match('/(tempmail|disposablemail|tmpmail|mailtemp|mailtostemp|yopmail|mailinator|guerrillamail)/i', $domain)) {
            return true;
        }

        // Verify active email servers using MX records (skip during testing to prevent network dependencies in unit tests)
        if (!app()->environment('testing')) {
            if (!checkdnsrr($domain, 'MX')) {
                return true;
            }
        }

        return false;
    }
}
