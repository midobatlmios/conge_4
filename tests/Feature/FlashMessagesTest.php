<?php

use App\Enums\Role;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('the message of an action reaches the next page once, for the toast', function () {
    $rh = User::factory()->create(['role' => Role::Hr]);
    $department = Department::create(['name' => 'Logistique']);

    $this->actingAs($rh)
        ->from('/admin/departments')
        ->followingRedirects()
        ->delete("/admin/departments/{$department->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('flash.success', '« Logistique » a été supprimé.')
            ->where('flash.error', null));

    // Shown once: the next visit has no message.
    $this->actingAs($rh)->get('/admin/departments')
        ->assertInertia(fn (Assert $page) => $page->where('flash.success', null));
});
