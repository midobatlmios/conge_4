<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

// Every page reads the company branding from the database.
uses(RefreshDatabase::class);

it('returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
