<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcripts', function (Blueprint $table) {
            $table->id();
            $table->uuid('clip_project_id');
            $table->foreign('clip_project_id')->references('id')->on('clip_projects')->cascadeOnDelete();
            $table->string('language', 10)->default('id');
            $table->json('content'); // [{start, end, text}]
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcripts');
    }
};
