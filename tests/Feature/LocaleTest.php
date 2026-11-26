<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('French by default; a signed-in user saves Arabic on the account, with a right-to-left page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('locale', 'fr'));

    $this->actingAs($user)->put('/locale', ['locale' => 'ar'])->assertRedirect();
    expect($user->fresh()->locale)->toBe('ar');

    $this->actingAs($user)->get('/dashboard')
        ->assertSee('dir="rtl"', false)
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'ar'));
});

test('on the sign-in page the choice is kept in the session; unknown languages are refused', function () {
    $this->put('/locale', ['locale' => 'en'])->assertRedirect();
    $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));

    $this->put('/locale', ['locale' => 'de'])->assertSessionHasErrors('locale');
});
