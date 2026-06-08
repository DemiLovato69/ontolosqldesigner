<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam('name', 'string', 'The diagram name. Must be unique per user.', required: false, example: 'My ERD')]
#[BodyParam('db_type', 'string', 'The diagram output type. Allowed: mysql, postgresql, sqlite, oracle, sqlserver, msaccess, ontology.', required: false, example: 'ontology')]
class DiagramRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('diagrams')->where('user_id', $this->user()->id)->ignore($this->route('diagram'))],
            'db_type' => ['sometimes', 'string', 'in:mysql,postgresql,sqlite,oracle,sqlserver,msaccess,ontology'],
            'share_access' => ['nullable', 'string', 'in:read,write,per_user'],
            'library' => ['sometimes', 'boolean'],
            'schema' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
