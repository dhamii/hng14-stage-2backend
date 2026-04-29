<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_states', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('state')->unique();
            $table->text('code_challenge');
            $table->string('code_challenge_method')->default('S256');
            $table->string('redirect_uri');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_states');
    }
};
