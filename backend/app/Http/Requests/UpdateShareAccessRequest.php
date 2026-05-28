<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShareAccessRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'access'           => ['sometimes', 'nullable', 'string', 'in:read,write,per_user'],
            'require_approval' => ['sometimes', 'nullable', 'boolean'],
            'library'          => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
