<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LibraryControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_library_page_returns_ok(): void
    {
        $this->get('/library')->assertStatus(200);
    }
}
