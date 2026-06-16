<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagram_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagram_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('access')->default('read');
            $table->timestamps();
            $table->unique(['diagram_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagram_invites');
    }
};
