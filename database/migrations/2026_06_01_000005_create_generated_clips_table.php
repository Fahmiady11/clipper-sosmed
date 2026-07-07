<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_clips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('clip_project_id');
            $table->foreign('clip_project_id')->references('id')->on('clip_projects')->cascadeOnDelete();
            $table->unsignedTinyInteger('ranking');
            $table->decimal('start_seconds', 8, 2);
            $table->decimal('end_seconds', 8, 2);
            $table->string('topic');
            $table->text('reason');
            $table->enum('viral_potential', ['tinggi', 'sedang', 'rendah'])->default('sedang');
            $table->string('hook_text', 100)->nullable();
            $table->json('subtitle_json')->nullable(); // [{start, end, text}]
            $table->string('output_path')->nullable();
            $table->enum('status', ['pending', 'processing', 'done', 'failed'])->default('pending');
            $table->text('error_msg')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_clips');
    }
};
