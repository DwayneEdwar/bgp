<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('router_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('router_id')->nullable()->constrained('routers')->nullOnDelete();
            $table->unsignedTinyInteger('cpu_load')->nullable();
            $table->unsignedBigInteger('memory_total')->nullable();
            $table->unsignedBigInteger('memory_free')->nullable();
            $table->string('uptime', 100)->nullable();
            $table->timestamp('sampled_at')->index();
            $table->index(['router_id', 'sampled_at']);
        });

        Schema::create('traffic_samples', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('router_id')->nullable()->constrained('routers')->nullOnDelete();
            $table->string('interface_name');
            $table->unsignedBigInteger('rx_bytes')->nullable();
            $table->unsignedBigInteger('tx_bytes')->nullable();
            $table->unsignedBigInteger('rx_packets')->nullable();
            $table->unsignedBigInteger('tx_packets')->nullable();
            $table->unsignedBigInteger('rx_bps')->nullable();
            $table->unsignedBigInteger('tx_bps')->nullable();
            $table->unsignedBigInteger('rx_pps')->nullable();
            $table->unsignedBigInteger('tx_pps')->nullable();
            $table->timestamp('sampled_at')->index();
            $table->index(['router_id', 'interface_name', 'sampled_at'], 'traffic_samples_router_interface_sampled_idx');
        });

        Schema::create('availability_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('router_id')->nullable()->constrained('routers')->nullOnDelete();
            $table->string('status', 20)->index();
            $table->timestamp('checked_at')->index();
            $table->index(['router_id', 'checked_at']);
        });

        Schema::create('alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('router_id')->nullable()->constrained('routers')->nullOnDelete();
            $table->string('type', 80)->index();
            $table->string('severity', 20)->index();
            $table->string('message', 500);
            $table->string('status', 20)->default('open')->index();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'severity', 'created_at']);
        });

        Schema::create('network_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('router_id')->nullable()->constrained('routers')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 80)->index();
            $table->string('module', 80)->index();
            $table->string('target_type', 120)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('description', 500);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['module', 'created_at']);
        });

        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80)->index();
            $table->string('module', 80)->index();
            $table->string('target_type', 120)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('description', 500);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['module', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('network_events');
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('availability_checks');
        Schema::dropIfExists('traffic_samples');
        Schema::dropIfExists('router_metrics');
    }
};
