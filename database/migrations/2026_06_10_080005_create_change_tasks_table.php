<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('change_id')->constrained('project_changes')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->text('description');
            $table->boolean('is_done')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_tasks');
    }
};
