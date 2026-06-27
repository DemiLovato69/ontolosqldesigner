<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Diagram;
use App\Models\DiagramFoundryConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiagramFoundryConfig>
 */
class DiagramFoundryConfigFactory extends Factory
{
    public function definition(): array
    {
        return [
            'diagram_id' => Diagram::factory(),
            'host_url' => 'https://'.fake()->domainWord().'.palantirfoundry.com',
            'default_project_rid' => null,
            'default_folder_rid' => null,
            'default_ontology_rid' => null,
            'settings' => null,
        ];
    }
}
