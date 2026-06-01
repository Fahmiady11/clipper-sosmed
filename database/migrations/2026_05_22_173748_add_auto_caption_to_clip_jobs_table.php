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
        Schema::table('clip_jobs', function (Blueprint $table) {
            $table->boolean('auto_caption')->default(false)->after('overlay_fontsize');
            $table->string('caption_language', 10)->nullable()->after('auto_caption');
        });
    }

    public function down(): void
    {
        Schema::table('clip_jobs', function (Blueprint $table) {
            $table->dropColumn(['auto_caption', 'caption_language']);
        });
    }
};
