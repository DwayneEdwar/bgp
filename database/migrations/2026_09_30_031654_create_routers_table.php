<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('routers', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(8728);

            $table->string('username');
            $table->text('password');

            $table->string('connection_type')
                ->default('api');

            $table->string('status')
                ->default('unknown');

            $table->timestamp('last_connected_at')
                ->nullable();
                
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routers');
    }
};
