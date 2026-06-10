<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('change_id')->constrained('project_changes')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('title');
            $table->text('body')->nullable();
            $table->boolean('is_checked')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_decisions');
    }
};
