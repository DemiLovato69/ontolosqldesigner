<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam('email', 'string', 'The admin email.', example: 'admin@example.com')]
#[BodyParam('password', 'string', 'The admin password.', example: 'secret')]
class AdminLoginRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ];
    }
}
