<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVisitorAccessRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'access' => ['required', 'string', 'in:read,write,revoke'],
        ];
    }
}
