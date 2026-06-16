<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeAdminUser extends Command
{
    protected $signature = 'admin:make {email} {--password=}';

    protected $description = 'Create or promote a user to admin.';

    public function handle(): int
    {
        $email = strtolower((string) $this->argument('email'));
        $password = $this->option('password');

        $user = User::where('email', $email)->first();

        if (! $user) {
            if (! is_string($password) || $password === '') {
                $password = $this->secret('Password for the new admin user');
            }

            if (! is_string($password) || strlen($password) < 8) {
                $this->error('Password must be at least 8 characters.');

                return self::FAILURE;
            }

            $user = User::create([
                'email' => $email,
                'password' => $password,
                'email_verified_at' => now(),
            ]);
        }

        $user->forceFill(['role' => 'admin'])->save();

        $this->info("{$email} is now an admin.");

        return self::SUCCESS;
    }
}
