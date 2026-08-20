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
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('server_id')->constrained('servers');
            $table->foreignId('user_id')->constrained('users');
            $table->string('domain');
            $table->string('type');
            $table->string('php_version')->nullable();
            $table->string('web_directory')->nullable();
            $table->string('root_path')->nullable();
            $table->string('repository_url')->nullable();
            $table->bigInteger('repository_id')->nullable();
            $table->bigInteger('deploy_key_id')->nullable();
            $table->string('repository_branch')->nullable();
            $table->string('deploy_key_fingerprint')->nullable();
            $table->json('deployment_options')->nullable();
            $table->unsignedBigInteger('current_release_id')->nullable();
            $table->enum('status', ['pending', 'deploying', 'deployed', 'failed'])->default('pending');
            $table->timestamp('last_deployed_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
