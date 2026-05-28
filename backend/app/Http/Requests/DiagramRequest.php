<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam("name", "string", "The diagram name. Must be unique per user.", required: false, example: "My ERD")]
#[BodyParam("db_type", "string", "The database type. Allowed: mysql, postgresql, sqlite, oracle, sqlserver, msaccess.", required: false, example: "postgresql")]
class DiagramRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['string', 'max:255'],
            'db_type' => ['sometimes', 'string', 'in:mysql,postgresql,sqlite,oracle,sqlserver,msaccess'],
            'share_access' => ['nullable', 'string', 'in:read,write,per_user'],
            'library' => ['sometimes', 'boolean'],
        ];
    }
}
