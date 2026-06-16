<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

trait HandlesLargeDiagramSchema
{
    /** @return array<string, mixed> */
    public function validationData(): array
    {
        $data = parent::validationData();
        unset($data['schema']);
        unset($data['value_types']);

        return $data;
    }

    /** @return array<int, mixed>|null */
    public function diagramSchema(): ?array
    {
        $schema = $this->input('schema');

        return is_array($schema) ? $schema : null;
    }

    protected function validateDiagramSchema(Validator $validator, bool $required, bool $nullable): void
    {
        if (! $this->exists('schema')) {
            if ($required) {
                $validator->errors()->add('schema', 'The schema field is required.');
            }

            return;
        }

        $schema = $this->input('schema');
        if ($schema === null && ! $nullable) {
            $validator->errors()->add('schema', 'The schema field is required.');

            return;
        }

        if ($schema !== null && ! is_array($schema)) {
            $validator->errors()->add('schema', 'The schema field must be an array.');
        }
    }
}
