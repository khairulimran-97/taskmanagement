<?php

use App\Models\User;
use App\Notifications\LoginOtpNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Request an OTP for the user and return the plaintext code captured
 * from the sent notification.
 */
function requestOtpCode(User $user): string
{
    Notification::fake();

    test()->post(route('login.otp.send'), ['email' => $user->email])
        ->assertSessionHasNoErrors();

    $code = null;
    Notification::assertSentTo($user, LoginOtpNotification::class, function ($notification) use (&$code) {
        $code = $notification->code;

        return true;
    });

    expect($code)->toMatch('/^\d{6}$/');

    return $code;
}

test('a user can request a login code', function () {
    Notification::fake();
    $user = User::factory()->create();

    $response = $this->post(route('login.otp.send'), ['email' => $user->email]);

    $response->assertSessionHasNoErrors();
    $response->assertSessionHas('status');
    Notification::assertSentTo($user, LoginOtpNotification::class);
});

test('requesting a code for an unknown email does not send anything but still succeeds', function () {
    Notification::fake();

    $response = $this->post(route('login.otp.send'), ['email' => 'nobody@example.com']);

    $response->assertSessionHasNoErrors();
    $response->assertSessionHas('status');
    Notification::assertNothingSent();
});

test('a user can sign in with a valid code', function () {
    $user = User::factory()->create();
    $code = requestOtpCode($user);

    $response = $this->post(route('login.otp.verify'), [
        'email' => $user->email,
        'code' => $code,
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('a user can not sign in with an invalid code', function () {
    $user = User::factory()->create();
    requestOtpCode($user);

    $response = $this->post(route('login.otp.verify'), [
        'email' => $user->email,
        'code' => '000000',
    ]);

    $response->assertSessionHasErrors('code');
    $this->assertGuest();
});

test('a code can only be used once', function () {
    $user = User::factory()->create();
    $code = requestOtpCode($user);

    $this->post(route('login.otp.verify'), [
        'email' => $user->email,
        'code' => $code,
    ]);
    $this->assertAuthenticatedAs($user);

    // Log out and try to reuse the same code.
    $this->post('/logout');

    $response = $this->post(route('login.otp.verify'), [
        'email' => $user->email,
        'code' => $code,
    ]);

    $response->assertSessionHasErrors('code');
    $this->assertGuest();
});

test('verifying without a code fails validation', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.otp.verify'), [
        'email' => $user->email,
        'code' => '',
    ]);

    $response->assertSessionHasErrors('code');
    $this->assertGuest();
});

test('the remember option is honored on otp sign in', function () {
    $user = User::factory()->create();
    $code = requestOtpCode($user);

    $response = $this->post(route('login.otp.verify'), [
        'email' => $user->email,
        'code' => $code,
        'remember' => true,
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertCookie(Auth::guard()->getRecallerName());
});
