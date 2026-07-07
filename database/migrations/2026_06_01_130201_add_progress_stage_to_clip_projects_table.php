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
        Schema::table('clip_projects', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress_stage')->default(0)->after('status');
            $table->string('progress_message', 120)->nullable()->after('progress_stage');
        });
    }

    public function down(): void
    {
        Schema::table('clip_projects', function (Blueprint $table) {
            $table->dropColumn(['progress_stage', 'progress_message']);
        });
    }
};
