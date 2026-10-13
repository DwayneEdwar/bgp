<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routers', function (Blueprint $table): void {
            $table->unique(['host', 'port'], 'routers_host_port_unique');
        });
    }

    public function down(): void
    {
        Schema::table('routers', function (Blueprint $table): void {
            $table->dropUnique('routers_host_port_unique');
        });
    }
};
