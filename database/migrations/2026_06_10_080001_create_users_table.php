<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('github_username');
            $table->unsignedBigInteger('github_user_id')->nullable();
            $table->string('avatar_url')->nullable();
            $table->text('access_token'); // 使用者輸入後由 Eloquent encrypted cast 加密儲存
            $table->string('account')->unique(); // 本站登入帳號
            $table->string('password'); // 本站登入密碼,由 model hashed cast 雜湊
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
