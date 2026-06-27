<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagram_agent_messages', function (Blueprint $table): void {
            // One turn in an agent session. Prompt/response/patch bodies are
            // encrypted at the model layer; only counts/usage stay in the clear.
            $table->id();
            $table->foreignId('session_id')->constrained('diagram_agent_sessions')->cascadeOnDelete();
            $table->foreignId('diagram_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role');
            $table->string('model')->nullable();
            $table->longText('prompt')->nullable();
            $table->longText('response')->nullable();
            $table->longText('patch')->nullable();
            $table->json('warnings')->nullable();
            $table->json('context_summary')->nullable();
            $table->json('usage')->nullable();
            $table->string('status')->default('completed');
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'id']);
            $table->index('diagram_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagram_agent_messages');
    }
};
