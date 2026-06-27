<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('foundry_connections', function (Blueprint $table): void {
            $table->string('auth_type')->default('oauth')->after('host_url');
        });
    }

    public function down(): void
    {
        Schema::table('foundry_connections', function (Blueprint $table): void {
            $table->dropColumn('auth_type');
        });
    }
};
