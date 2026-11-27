<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SchemaSpa;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use SchemaSpa;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->taoCauTrucSpa();
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
