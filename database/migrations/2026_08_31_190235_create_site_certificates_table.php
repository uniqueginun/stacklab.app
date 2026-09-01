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
        Schema::create('site_certificates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('status');
            $table->json('domains');
            $table->text('csr')->nullable();
            $table->text('certificate')->nullable();
            $table->text('private_key')->nullable();
            $table->text('chain')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_certificates');
    }
};
