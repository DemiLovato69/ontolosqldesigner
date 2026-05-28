<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveByTokenRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'schema' => ['required', 'array'],
        ];
    }
}
