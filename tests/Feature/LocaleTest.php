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

test('server messages follow the account language: validation errors and notices', function () {
    $user = User::factory()->create(['locale' => 'ar']);

    $this->actingAs($user)->patch('/settings/profile', ['name' => '', 'email' => $user->email])
        ->assertSessionHasErrors(['name' => 'حقل الاسم إلزامي.']);

    $this->actingAs($user)->withSession(['success' => 'Données personnelles enregistrées.'])->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('flash.success', 'حُفظت المعطيات الشخصية.'));

    // French by default, and e-mails go out in the recipient's language.
    expect(User::factory()->create()->preferredLocale())->toBe('fr')
        ->and($user->preferredLocale())->toBe('ar');
});
