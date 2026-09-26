<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clip_projects', function (Blueprint $table) {
            $table->boolean('music_enabled')->default(false)->after('max_duration');
            $table->string('music_mood', 32)->nullable()->after('music_enabled'); // null = AI picks per clip
            $table->unsignedTinyInteger('music_volume')->default(15)->after('music_mood'); // percent
        });

        Schema::table('generated_clips', function (Blueprint $table) {
            $table->string('music_mood', 32)->nullable()->after('hashtags_json');
            $table->string('music_track')->nullable()->after('music_mood');
        });
    }

    public function down(): void
    {
        Schema::table('clip_projects', function (Blueprint $table) {
            $table->dropColumn(['music_enabled', 'music_mood', 'music_volume']);
        });

        Schema::table('generated_clips', function (Blueprint $table) {
            $table->dropColumn(['music_mood', 'music_track']);
        });
    }
};
