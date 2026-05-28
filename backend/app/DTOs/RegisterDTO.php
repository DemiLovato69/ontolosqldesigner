<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class RegisterDTO
{
    public function __construct(
        public string $email,
        #[\SensitiveParameter] public string $password,
    ) {}
}
