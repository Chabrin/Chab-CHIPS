<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The home page is not public: a guest is sent on to the login page.
     */
    public function test_a_guest_is_redirected_away_from_the_home_page(): void
    {
        $response = $this->get('/');

        $response->assertRedirect();
    }
}
