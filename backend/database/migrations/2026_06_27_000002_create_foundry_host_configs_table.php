<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foundry_host_configs', function (Blueprint $table): void {
            $table->id();
            $table->string('host_url')->unique();
            $table->string('display_name')->nullable();
            $table->string('client_id');
            $table->text('client_secret')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foundry_host_configs');
    }
};
