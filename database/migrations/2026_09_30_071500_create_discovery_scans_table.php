<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discovery_scans', function (Blueprint $table): void {
            $table->id();
            $table->string('subnet', 43);
            $table->json('ports');
            $table->string('status', 20)->default('queued')->index();
            $table->unsignedInteger('devices_found')->default(0);
            $table->string('failure_code', 80)->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discovery_scans');
    }
};
