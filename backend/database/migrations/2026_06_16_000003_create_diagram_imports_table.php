<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagram_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagram_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('format', 32);
            $table->string('status', 32)->default('uploading');
            $table->string('disk')->default('imports');
            $table->string('directory');
            $table->string('path')->nullable();
            $table->string('original_name')->nullable();
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('chunk_size');
            $table->unsignedInteger('chunks_total');
            $table->json('chunks_received')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['diagram_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagram_imports');
    }
};
