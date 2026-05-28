<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\SupportMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendSupportEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 30;
    public int $backoff = 60;

    public function __construct(
        private readonly string $body,
        private readonly ?string $senderEmail,
    ) {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        Mail::to(config('mail.mailers.smtp.username'))->send(new SupportMail($this->body, $this->senderEmail));
    }
}
