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
        Schema::table('generated_clips', function (Blueprint $table) {
            $table->text('caption')->nullable()->after('subtitle_json');
            $table->json('hashtags_json')->nullable()->after('caption');
        });
    }

    public function down(): void
    {
        Schema::table('generated_clips', function (Blueprint $table) {
            $table->dropColumn(['caption', 'hashtags_json']);
        });
    }
};
