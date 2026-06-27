<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagram_foundry_configs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('diagram_id')->constrained()->cascadeOnDelete();
            $table->string('host_url')->nullable();
            $table->string('default_project_rid')->nullable();
            $table->string('default_folder_rid')->nullable();
            $table->string('default_ontology_rid')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique('diagram_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagram_foundry_configs');
    }
};
