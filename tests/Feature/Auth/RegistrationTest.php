<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// Public registration is deliberately disabled: the GET route redirects away
// and the POST route no longer exists (see routes/auth.php).

test('the registration screen redirects away', function () {
    $this->get('/register')->assertRedirect();
});

test('registration submissions are rejected', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertStatus(405);
    $this->assertGuest();
});
