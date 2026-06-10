<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('full_name')->unique(); // 例:virtualorz/spectrum
            $table->string('display_name')->nullable(); // ← GitHub repo description
            $table->string('tech_stack')->nullable(); // ← GitHub language(框架/版本日後解析 composer.json 補)
            $table->string('default_branch')->nullable();
            $table->boolean('is_private')->default(false);
            $table->boolean('has_specflow')->default(false); // 對應頁面 flow
            $table->boolean('is_tracked')->default(false); // 對應 setup 勾選
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
