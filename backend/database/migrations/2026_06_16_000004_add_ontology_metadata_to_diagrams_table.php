<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diagrams', function (Blueprint $table): void {
            $table->json('interfaces')->nullable();
            $table->json('interface_link_constraints')->nullable();
            $table->json('custom_actions')->nullable();
            $table->json('shared_property_types')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('diagrams', function (Blueprint $table): void {
            $table->dropColumn([
                'interfaces',
                'interface_link_constraints',
                'custom_actions',
                'shared_property_types',
            ]);
        });
    }
};
