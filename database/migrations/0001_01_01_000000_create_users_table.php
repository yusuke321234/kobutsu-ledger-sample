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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('family_name');   // 姓（必須）
            $table->string('given_name');    // 名（必須）
            $table->string('email')->unique();
            $table->datetime('email_verified_at')->nullable();   // Laravel 標準（このアプリでは未使用）。DATETIME
            $table->string('password');
            $table->rememberToken();
            $table->datetimes();             // created_at / updated_at（DATETIME。2038年問題回避、05-database-conventions）
            $table->softDeletesDatetime();   // deleted_at（DATETIME。原則ソフトデリート）
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->datetime('created_at')->nullable();   // DATETIME（2038年問題回避）
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
