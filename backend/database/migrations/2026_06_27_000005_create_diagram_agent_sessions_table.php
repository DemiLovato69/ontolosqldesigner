<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagram_agent_sessions', function (Blueprint $table): void {
            // A diagram agent conversation. Sessions are visible to every diagram
            // collaborator (read access) and are archived rather than deleted.
            $table->id();
            $table->foreignId('diagram_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('foundry_host_url')->nullable();
            $table->string('title')->nullable();
            $table->string('model')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['diagram_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagram_agent_sessions');
    }
};
