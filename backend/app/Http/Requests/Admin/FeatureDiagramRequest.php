<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FeatureDiagramRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'url' => 'required|url|max:500',
        ];
    }
}
