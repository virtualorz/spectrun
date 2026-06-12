<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // 該 project 的 specflow 在哪個分支(單一分支名,非 JSON);null → 同步 fallback default_branch
            $table->string('specflow_branch')->nullable()->after('default_branch');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('specflow_branch');
        });
    }
};
