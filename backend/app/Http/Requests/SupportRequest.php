<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam("email", "string", "The sender's email address.", required: false, example: "user@example.com")]
#[BodyParam("message", "string", "The support message.", example: "I need help with this tool!")]
class SupportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => 'nullable|email|max:255',
            'message' => 'required|string|max:5000',
        ];
    }
}
