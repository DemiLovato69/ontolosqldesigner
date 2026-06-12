<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidSchemaException;
use Symfony\Component\Process\Process;

class MakerDefinitionImportService
{
    /** @return array<string, mixed> */
    public function convert(string $module): array
    {
        $script = (string) config('services.maker_import.script');
        $node = (string) config('services.maker_import.node', 'node');
        if ($script === '' || ! is_file($script)) {
            throw new InvalidSchemaException('Maker definition import runtime is not installed.');
        }

        $process = new Process([$node, $script]);
        $process->setInput($module);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            $message = trim($process->getErrorOutput()) ?: 'Maker definition import failed.';
            throw new InvalidSchemaException($message);
        }

        $decoded = json_decode($process->getOutput(), true);
        if (! is_array($decoded)) {
            throw new InvalidSchemaException('Maker definition importer returned invalid ontology JSON.');
        }

        return $decoded;
    }
}
