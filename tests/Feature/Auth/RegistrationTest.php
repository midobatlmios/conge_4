<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('public registration is disabled', function () {
    $this->get('/register')->assertNotFound();

    $this->post('/register', [
        'name' => 'Intruder',
        'email' => 'intruder@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'intruder@example.com']);
});
