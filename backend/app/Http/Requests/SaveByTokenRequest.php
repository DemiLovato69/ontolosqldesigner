<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HandlesLargeDiagramSchema;
use App\Rules\ValueTypeDefinitions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveByTokenRequest extends FormRequest
{
    use HandlesLargeDiagramSchema;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'value_types' => ['sometimes', 'array', new ValueTypeDefinitions],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateDiagramSchema($validator, true, false);

            $valueTypes = $this->input('value_types', []);
            if ($this->has('value_types')) {
                $rule = new ValueTypeDefinitions;
                $rule->validate('value_types', $valueTypes, fn (string $message) => $validator->errors()->add('value_types', $message));
            }

            if (! is_array($valueTypes)) {
                return;
            }

            $ids = array_fill_keys(array_filter(array_map(
                fn (mixed $definition): ?string => is_array($definition) && is_string($definition['id'] ?? null)
                    ? $definition['id']
                    : null,
                $valueTypes
            )), true);

            foreach ($this->diagramSchema() ?? [] as $item) {
                $reference = is_array($item) ? ($item['data']['valueTypeId'] ?? null) : null;
                if (is_string($reference) && $reference !== '' && ! isset($ids[$reference])) {
                    $validator->errors()->add('schema', "Schema row references missing value type {$reference}.");

                    return;
                }
            }
        });
    }
}
