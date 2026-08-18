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
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->uuid()->unique();
            $table->string('name');
            $table->string('provider');
            $table->string('host');
            $table->string('ssh_port');
            $table->string('ssh_user');
            $table->text('ssh_private_key')->nullable();
            $table->text('ssh_public_key')->nullable();
            $table->string('known_hosts_path')->nullable();
            $table->string('host_key_fingerprint')->nullable();
            $table->text('host_key')->nullable();
            $table->json('server_info')->nullable();
            $table->enum('connection_status', ['unverified', 'pending_confirmation', 'connected', 'failed'])->default('unverified');
            $table->string('profile')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
