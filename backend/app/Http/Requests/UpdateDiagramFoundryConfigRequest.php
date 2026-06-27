<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDiagramFoundryConfigRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'host_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'default_project_rid' => ['sometimes', 'nullable', 'string', 'max:255'],
            'default_folder_rid' => ['sometimes', 'nullable', 'string', 'max:255'],
            'default_ontology_rid' => ['sometimes', 'nullable', 'string', 'max:255'],
            'settings' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
