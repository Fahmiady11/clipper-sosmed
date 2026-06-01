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
        Schema::create('clip_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('youtube_url', 500);
            $table->integer('start_time');
            $table->integer('end_time');
            $table->enum('status', ['pending', 'processing', 'done', 'failed'])->default('pending');
            $table->string('file_path', 500)->nullable();
            $table->text('error_msg')->nullable();
            $table->string('overlay_text')->nullable();
            $table->string('overlay_position')->nullable()->default('bottom_right');
            $table->string('overlay_color')->nullable()->default('white');
            $table->integer('overlay_fontsize')->nullable()->default(36);
            $table->timestamps();
            $table->timestamp('expires_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clip_jobs');
    }
};
