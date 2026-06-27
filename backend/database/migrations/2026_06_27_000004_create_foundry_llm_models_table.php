<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foundry_llm_models', function (Blueprint $table): void {
            // Admin-managed allowlist of Foundry AIP models the diagram agent may
            // call. A null host_url makes the model available on every host.
            $table->id();
            $table->string('host_url')->nullable();
            $table->string('provider')->default('openai');
            $table->string('model');
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('max_output_tokens')->nullable();
            $table->decimal('temperature', 3, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['host_url', 'model']);
            $table->index(['enabled', 'host_url']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foundry_llm_models');
    }
};
