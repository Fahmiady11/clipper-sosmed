<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clip_projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('youtube_url');
            $table->string('video_title')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->enum('layout_type', ['reframe', 'gaussian'])->default('reframe');
            $table->unsignedTinyInteger('clip_count')->default(3);
            $table->enum('duration_mode', ['auto', 'manual'])->default('auto');
            $table->unsignedSmallInteger('min_duration')->nullable();
            $table->unsignedSmallInteger('max_duration')->nullable();
            $table->enum('status', ['draft', 'processing', 'done', 'failed'])->default('draft');
            $table->text('error_msg')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clip_projects');
    }
};
