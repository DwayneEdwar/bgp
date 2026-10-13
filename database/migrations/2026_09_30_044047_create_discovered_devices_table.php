<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discovered_devices', function (Blueprint $table) {
            $table->id();

            $table->string('ip_address');
            $table->unsignedSmallInteger('api_port')->default(8728);

            $table->string('hostname')->nullable();
            $table->string('identity')->nullable();
            $table->string('device_type')->nullable();

            $table->boolean('api_available')->default(false);

            $table->string('status')->default('unknown');

            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            $table->foreignId('router_id')
                ->nullable()
                ->constrained('routers')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique([
                'ip_address',
                'api_port',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discovered_devices');
    }
};