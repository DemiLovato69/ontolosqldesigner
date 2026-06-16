<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HandlesLargeDiagramSchema;
use App\Rules\ValueTypeDefinitions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Knuckles\Scribe\Attributes\BodyParam;

#[BodyParam('name', 'string', 'The diagram name. Must be unique per user.', required: false, example: 'My ERD')]
#[BodyParam('db_type', 'string', 'The diagram output type. Allowed: mysql, postgresql, sqlite, oracle, sqlserver, msaccess, ontology.', required: false, example: 'ontology')]
class DiagramRequest extends FormRequest
{
    use HandlesLargeDiagramSchema;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('diagrams')->where('user_id', $this->user()->id)->ignore($this->route('diagram'))],
            'db_type' => ['sometimes', 'string', 'in:mysql,postgresql,sqlite,oracle,sqlserver,msaccess,ontology'],
            'share_access' => ['nullable', 'string', 'in:read,write,per_user'],
            'library' => ['sometimes', 'boolean'],
            'value_types' => ['sometimes', 'array', new ValueTypeDefinitions],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateDiagramSchema($validator, false, true);

            $valueTypes = $this->input('value_types', []);
            if ($this->has('value_types')) {
                $rule = new ValueTypeDefinitions;
                $rule->validate('value_types', $valueTypes, fn (string $message) => $validator->errors()->add('value_types', $message));
            }

            if (! is_array($valueTypes)) {
                return;
            }

            $diagram = $this->route('diagram');
            $dbType = (string) ($this->input('db_type') ?? ($diagram?->db_type->value ?? ''));
            if ($valueTypes !== [] && $dbType !== 'ontology') {
                $validator->errors()->add('value_types', 'Value types are only available for ontology diagrams.');
            }

            $this->validateReferences($validator, $valueTypes);
        });
    }

    /** @param list<array<string, mixed>> $valueTypes */
    private function validateReferences(Validator $validator, array $valueTypes): void
    {
        $ids = [];
        foreach ($valueTypes as $definition) {
            if (is_string($definition['id'] ?? null)) {
                $ids[$definition['id']] = true;
            }
        }

        foreach ($this->diagramSchema() ?? [] as $item) {
            $reference = is_array($item) ? ($item['data']['valueTypeId'] ?? null) : null;
            if (is_string($reference) && $reference !== '' && ! isset($ids[$reference])) {
                $validator->errors()->add('schema', "Schema row references missing value type {$reference}.");

                return;
            }
        }
    }
}
