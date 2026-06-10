<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('change_id')->constrained('project_changes')->cascadeOnDelete();
            $table->string('path');
            $table->string('change_kind')->nullable(); // created / modified
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_files');
    }
};
