<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('number'); // 例:0001
            $table->string('slug');
            $table->string('title');
            $table->text('problem')->nullable();
            $table->string('base_branch')->nullable();
            $table->string('status')->nullable(); // proposed/designing/running/closed

            // 各階段時間點(頁面的 dDesign/dRun/dClose 由此相減推導)
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('designed_at')->nullable();
            $table->timestamp('ran_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            // tokens(頁面 tokens = at_close - at_new)
            $table->unsignedBigInteger('tokens_at_new')->nullable();
            $table->unsignedBigInteger('tokens_at_close')->nullable();

            $table->text('deviation')->nullable(); // 對應頁面 dev(偏離原計畫)

            // 子項彙總計數(同步時更新,供列表頁進度條)
            $table->unsignedInteger('decisions_done')->default(0);
            $table->unsignedInteger('decisions_total')->default(0);
            $table->unsignedInteger('tasks_done')->default(0);
            $table->unsignedInteger('tasks_total')->default(0);
            $table->unsignedInteger('discussion_count')->default(0);

            $table->timestamps();

            $table->unique(['project_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_changes');
    }
};
