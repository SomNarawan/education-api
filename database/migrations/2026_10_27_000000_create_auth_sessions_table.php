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
        Schema::create('auth_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nontri_id')->index();
            $table->json('roles');
            // Encrypted with APP_KEY (AuthSession casts).
            $table->text('intranet_token');
            $table->text('sso_token')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('checked_at');
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason', 50)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auth_sessions');
    }
};
