<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SupportControllerTest extends TestCase
{

    public function test_send_support_message_returns_ok(): void
    {
        Queue::fake();

        $this->postJson('/api/support', [
            'message' => 'I need help with something.',
            'email' => 'user@example.com',
        ])->assertStatus(200)->assertJson(['status' => true]);
    }
}
