<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bgp_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('router_id')->nullable()->constrained('routers')->nullOnDelete();
            $table->string('peer_name', 255)->nullable();
            $table->string('remote_address', 255)->nullable();
            $table->unsignedBigInteger('remote_as')->nullable();
            $table->unsignedBigInteger('local_as')->nullable();
            $table->string('state', 40)->default('unknown')->index();
            $table->string('uptime', 100)->nullable();
            $table->unsignedInteger('prefixes_received')->nullable();
            $table->unsignedInteger('prefixes_sent')->nullable();
            $table->timestamp('checked_at')->index();
            $table->timestamps();
            $table->index(['router_id', 'remote_address']);
        });

        Schema::create('router_neighbors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('router_id')->nullable()->constrained('routers')->nullOnDelete();
            $table->foreignId('neighbor_router_id')->nullable()->constrained('routers')->nullOnDelete();
            $table->string('neighbor_identity', 255)->nullable();
            $table->string('neighbor_address', 45)->nullable();
            $table->string('local_interface', 255)->nullable();
            $table->string('platform', 255)->nullable();
            $table->string('protocol', 40)->nullable();
            $table->timestamp('observed_at')->index();
            $table->timestamps();
            $table->unique(['router_id', 'neighbor_address', 'local_interface'], 'router_neighbors_observation_unique');
            $table->index(['router_id', 'neighbor_router_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('router_neighbors');
        Schema::dropIfExists('bgp_sessions');
    }
};
