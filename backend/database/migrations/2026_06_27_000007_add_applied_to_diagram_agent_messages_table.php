<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diagram_agent_messages', function (Blueprint $table): void {
            // Tracks whether an assistant patch has been applied to the diagram,
            // so reopening the agent panel can't offer to apply it again.
            $table->timestamp('applied_at')->nullable()->after('status');
            $table->foreignId('applied_by_user_id')->nullable()->after('applied_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('diagram_agent_messages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('applied_by_user_id');
            $table->dropColumn('applied_at');
        });
    }
};
