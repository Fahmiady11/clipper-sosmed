<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clip_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('youtube_url', 500);
            $table->string('video_id', 20)->nullable();
            $table->string('layout_mode', 30);
            $table->tinyInteger('clip_count')->default(3);
            $table->string('status', 20)->default('pending');
            $table->text('error_msg')->nullable();
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clip_batches');
    }
};
