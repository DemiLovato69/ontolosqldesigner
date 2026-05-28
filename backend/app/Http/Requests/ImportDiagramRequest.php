<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportDiagramRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'script' => ['required', 'string'],
        ];
    }
}
