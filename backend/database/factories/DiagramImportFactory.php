<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Diagram;
use App\Models\DiagramImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<DiagramImport> */
class DiagramImportFactory extends Factory
{
    protected $model = DiagramImport::class;

    public function definition(): array
    {
        $directory = 'diagrams/'.Str::uuid()->toString();

        return [
            'diagram_id' => Diagram::factory(),
            'user_id' => User::factory(),
            'format' => 'sql',
            'status' => DiagramImport::STATUS_UPLOADING,
            'disk' => 'imports',
            'directory' => $directory,
            'path' => null,
            'original_name' => 'schema.sql',
            'size' => 32,
            'chunk_size' => 16,
            'chunks_total' => 2,
            'chunks_received' => [],
            'error' => null,
        ];
    }
}
