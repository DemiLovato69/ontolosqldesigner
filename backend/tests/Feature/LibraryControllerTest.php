<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class LibraryControllerTest extends TestCase
{

    public function test_library_page_returns_ok(): void
    {
        $this->get('/library')->assertStatus(200);
    }
}
