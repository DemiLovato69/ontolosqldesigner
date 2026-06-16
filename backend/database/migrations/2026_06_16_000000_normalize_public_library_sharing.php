<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('diagrams')
            ->where('library', true)
            ->update(['require_approval' => false]);

        DB::table('diagrams')
            ->where('library', true)
            ->where(function ($query) {
                $query->whereNull('share_access')
                    ->orWhere('share_access', '!=', 'write');
            })
            ->update(['share_access' => 'read']);
    }

    public function down(): void
    {
        // The previous per-diagram approval/access state cannot be reconstructed safely.
    }
};
