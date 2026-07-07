<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subtitle_settings', function (Blueprint $table) {
            $table->id();
            $table->uuid('clip_project_id');
            $table->foreign('clip_project_id')->references('id')->on('clip_projects')->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->string('font_family')->default('Montserrat');
            $table->unsignedSmallInteger('font_size')->default(42);
            $table->string('text_color', 9)->default('#ffffff');
            $table->string('highlight_color', 9)->default('#facc15');
            $table->enum('position', ['top', 'center', 'bottom'])->default('bottom');
            $table->enum('background_style', ['none', 'semi', 'full'])->default('semi');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subtitle_settings');
    }
};
