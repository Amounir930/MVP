<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): RedirectResponse
    {
        return redirect()->route('login', ['action' => 'forgot-password']);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ], [
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'البريد الإلكتروني غير صحيح.',
        ]);

        if (\App\Models\User::isDisposableEmail($request->email)) {
            throw ValidationException::withMessages([
                'email' => ['البريد الإلكتروني المدخل غير صالح.'],
            ]);
        }

        $user = \App\Models\User::where('email', $request->email)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['البريد الإلكتروني غير مسجل لدينا.'],
            ]);
        }

        \App\Models\User::sendOtpCode($request->email, 'password_reset', false);

        return back()
            ->with('status', 'code-sent')
            ->with('email', $request->email)
            ->with('success', 'تم إرسال رمز التحقق بنجاح إلى بريدك الإلكتروني.');
    }
}
