<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthRepository
{
    /**
     * @param  array{email: string, password: string}  $data
     */
    public function createNewUser(array $data): User
    {
        return User::create([
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
    }

    public function findUser(string $email): ?User
    {
        return User::where('email', $email)->first();
    }
}
