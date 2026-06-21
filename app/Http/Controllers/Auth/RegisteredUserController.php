<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        $email = $request->query('email');
        $token = $request->query('token');

        if (!$email || !$token) {
            return redirect('/')->with('error', 'يرجى طلب رمز تحقق أولاً لتسجيل حساب جديد.');
        }

        $valid = \App\Models\VerificationCode::where('email', $email)
            ->where('token', $token)
            ->where('type', 'register')
            ->where('expires_at', '>', now())
            ->exists();

        if (!$valid) {
            return redirect('/')->with('error', 'رمز التفعيل غير صالح أو انتهت صلاحيته. يرجى طلب رمز جديد.');
        }

        return redirect()->route('login', [
            'action' => 'register',
            'email' => $email,
            'otp_token' => $token,
        ]);
    }

    /**
     * Request a registration activation link.
     */
    public function requestActivationLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
        ], [
            'email.unique' => 'هذا البريد الإلكتروني مسجل لدينا بالفعل.',
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'البريد الإلكتروني غير صحيح.',
        ]);

        if (User::isDisposableEmail($request->email)) {
            throw ValidationException::withMessages([
                'email' => ['غير مسموح بالتسجيل باستخدام البريد الإلكتروني المؤقت أو الوهمي.'],
            ]);
        }

        $otpData = User::sendOtpCode($request->email, 'register', generateToken: true);

        return back()
            ->with('status', 'activation-sent')
            ->with('email', $request->email)
            ->with('otp_token', $otpData['token'])
            ->with('success', 'تم إرسال رمز التأكيد بنجاح إلى بريدك الإلكتروني.');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'code' => 'required|string|size:6',
            'token' => 'required|string',
        ], [
            'name.required' => 'الاسم مطلوب.',
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.unique' => 'هذا البريد الإلكتروني مسجل لدينا بالفعل.',
            'password.required' => 'كلمة المرور مطلوبة.',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
            'code.required' => 'رمز التأكيد مطلوب.',
            'code.size' => 'يجب أن يتكون رمز التأكيد من 6 أرقام.',
        ]);

        if (User::isDisposableEmail($request->email)) {
            throw ValidationException::withMessages([
                'email' => ['غير مسموح بالتسجيل باستخدام البريد الإلكتروني المؤقت أو الوهمي.'],
            ]);
        }

        // Verify OTP and token
        $otpRecord = \App\Models\VerificationCode::where('email', $request->email)
            ->where('code', $request->code)
            ->where('token', $request->token)
            ->where('type', 'register')
            ->where('expires_at', '>', now())
            ->first();

        if (!$otpRecord) {
            throw ValidationException::withMessages([
                'code' => ['رمز التأكيد غير صحيح أو انتهت صلاحيته.'],
            ]);
        }

        // Run the creation of Tenant, Subscription, and User inside a database transaction to prevent high concurrency race conditions
        $user = DB::transaction(function () use ($request, $otpRecord) {
            $tenant = \App\Models\Tenant::create([
                'name' => $request->name . ' Store',
            ]);

            \App\Models\Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_name' => 'free',
                'price' => 0.00,
                'status' => 'active',
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
                'monthly_limit' => 50,
                'current_period_usage' => 0,
                'gateway_token' => 'free_signup_' . uniqid(),
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'tenant_id' => $tenant->id,
                'email_verified_at' => now(),
            ]);

            // Delete the verified OTP inside transaction so it is atomically burned
            $otpRecord->delete();

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
