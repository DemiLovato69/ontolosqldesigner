<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\DiagramAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVisitorAccessRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'access' => ['required', Rule::enum(DiagramAccess::class)],
        ];
    }
}
