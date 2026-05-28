<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\RegisterDTO;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthRepository
{
    public function createNewUser(RegisterDTO $dto): User
    {
        return User::create([
            'email'    => $dto->email,
            'password' => Hash::make($dto->password),
        ]);
    }

    public function findUser(string $email): ?User
    {
        return User::where('email', $email)->first();
    }
}
