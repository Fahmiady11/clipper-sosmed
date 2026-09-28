<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_studio_requires_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_login_page_loads(): void
    {
        $this->get('/login')->assertOk();
    }
}
