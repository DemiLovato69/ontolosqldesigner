<?php

declare(strict_types=1);

namespace App\DTOs;

use SensitiveParameter;

readonly class RegisterDTO
{
    public function __construct(
        public string $email,
        #[SensitiveParameter] public string $password,
    ) {}
}
