<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\LoginOtpNotification;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OtpLoginController extends Controller
{
    private const EXPIRY_MINUTES = 10;

    /**
     * Send a one-time login code to the given email if it belongs to a user.
     */
    public function send(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $this->ensureIsNotRateLimited($this->sendThrottleKey($request), 5);

        RateLimiter::hit($this->sendThrottleKey($request), self::EXPIRY_MINUTES * 60);

        $user = User::where('email', $request->string('email')->value())->first();

        // Always behave the same way to avoid leaking which emails exist.
        if ($user) {
            $code = (string) random_int(100000, 999999);

            Cache::put(
                $this->cacheKey($user->email),
                ['hash' => Hash::make($code), 'attempts' => 0],
                now()->addMinutes(self::EXPIRY_MINUTES),
            );

            $user->notify(new LoginOtpNotification($code, self::EXPIRY_MINUTES));
        }

        return back()->with('status', 'If an account exists for that email, a sign-in code is on its way.');
    }

    /**
     * Verify the submitted code and log the user in.
     */
    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'code' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        $this->ensureIsNotRateLimited($this->verifyThrottleKey($request), 6);

        $email = $request->string('email')->value();
        $record = Cache::get($this->cacheKey($email));

        if (! $record || ! Hash::check($request->string('code')->value(), $record['hash'])) {
            RateLimiter::hit($this->verifyThrottleKey($request), self::EXPIRY_MINUTES * 60);

            // Burn the code after too many wrong attempts.
            if ($record && ($record['attempts'] + 1) >= 5) {
                Cache::forget($this->cacheKey($email));
            } elseif ($record) {
                Cache::put(
                    $this->cacheKey($email),
                    ['hash' => $record['hash'], 'attempts' => $record['attempts'] + 1],
                    now()->addMinutes(self::EXPIRY_MINUTES),
                );
            }

            throw ValidationException::withMessages([
                'code' => 'The code is invalid or has expired.',
            ]);
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'code' => 'The code is invalid or has expired.',
            ]);
        }

        Cache::forget($this->cacheKey($email));
        RateLimiter::clear($this->verifyThrottleKey($request));
        RateLimiter::clear($this->sendThrottleKey($request));

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function cacheKey(string $email): string
    {
        return 'login-otp:'.Str::lower($email);
    }

    private function sendThrottleKey(Request $request): string
    {
        return 'otp-send:'.Str::transliterate(Str::lower($request->string('email')->value()).'|'.$request->ip());
    }

    private function verifyThrottleKey(Request $request): string
    {
        return 'otp-verify:'.Str::transliterate(Str::lower($request->string('email')->value()).'|'.$request->ip());
    }

    private function ensureIsNotRateLimited(string $key, int $maxAttempts): void
    {
        if (! RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }
}
