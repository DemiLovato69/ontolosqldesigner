<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAdminBulkEmailBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 3600;

    public function __construct(
        private readonly string $subject,
        private readonly string $body,
    ) {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        $index = 0;

        User::query()->cursor()->each(function ($user) use (&$index) {
            SendAdminBulkEmail::dispatch($user->email, $this->subject, $this->body)
                ->delay(now()->addSeconds($index * 2));
            $index++;
        });
    }
}
