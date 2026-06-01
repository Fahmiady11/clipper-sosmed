<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clip_jobs', function (Blueprint $table) {
            $table->uuid('batch_id')->nullable()->after('id')->index();
            $table->string('layout_mode', 30)->nullable()->after('youtube_url');
            $table->text('gemini_hook')->nullable()->after('error_msg');
            $table->text('gemini_caption')->nullable()->after('gemini_hook');
            $table->json('gemini_hashtags')->nullable()->after('gemini_caption');
            $table->string('thumbnail_url', 500)->nullable()->after('gemini_hashtags');
        });
    }

    public function down(): void
    {
        Schema::table('clip_jobs', function (Blueprint $table) {
            $table->dropIndex(['batch_id']);
            $table->dropColumn(['batch_id', 'layout_mode', 'gemini_hook', 'gemini_caption', 'gemini_hashtags', 'thumbnail_url']);
        });
    }
};
